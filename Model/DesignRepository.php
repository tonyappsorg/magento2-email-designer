<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Model;

use Magento\Email\Model\ResourceModel\Template as TemplateResource;
use Magento\Email\Model\Template;
use Magento\Email\Model\TemplateFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\TemplateTypesInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * A design is an ordinary core email template plus the editor project it was built from. Saving writes both
 * and keeps the previous versions.
 */
class DesignRepository
{
    public const DESIGN_TABLE = 'tonyapps_email_design';
    public const VERSION_TABLE = 'tonyapps_email_design_version';
    public const KEEP_VERSIONS = 10;

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly TemplateFactory $templateFactory,
        private readonly TemplateResource $templateResource,
        private readonly TemplateVariables $templateVariables
    ) {
    }

    /** @return array<int, array<string, mixed>> every design with its template name and the settings using it */
    public function getList(): array
    {
        $connection = $this->resource->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(['design' => $this->resource->getTableName(self::DESIGN_TABLE)], ['design_id', 'template_id', 'base_template_code', 'updated_at'])
                ->join(
                    ['template' => $this->resource->getTableName('email_template')],
                    'template.template_id = design.template_id',
                    ['template_code', 'template_subject']
                )
                ->order('template.template_code ASC')
        );
        if (!$rows) {
            return [];
        }

        $usage = [];
        foreach ($connection->fetchAll(
            $connection->select()
                ->from($this->resource->getTableName('core_config_data'), ['path', 'scope', 'scope_id', 'value'])
                ->where('value IN (?)', array_map('strval', array_column($rows, 'template_id')))
                ->where('path LIKE ?', '%template%')
        ) as $setting) {
            $usage[(int)$setting['value']][] = $setting;
        }
        foreach ($rows as &$row) {
            $row['used_in'] = $usage[(int)$row['template_id']] ?? [];
        }
        unset($row);
        return $rows;
    }

    /** @return array<string, mixed> */
    public function get(int $designId): array
    {
        $connection = $this->resource->getConnection();
        $row = $connection->fetchRow(
            $connection->select()
                ->from(['design' => $this->resource->getTableName(self::DESIGN_TABLE)])
                ->join(
                    ['template' => $this->resource->getTableName('email_template')],
                    'template.template_id = design.template_id',
                    ['template_code', 'template_subject', 'template_text']
                )
                ->where('design.design_id = ?', $designId)
        );
        if (!$row) {
            throw new NoSuchEntityException(__('The email design no longer exists.'));
        }
        return $row;
    }

    /**
     * Creates or updates the design and its email template, and records a version.
     *
     * @return int design ID
     */
    public function save(
        ?int $designId,
        string $name,
        string $baseCode,
        string $subject,
        string $projectData,
        string $mjml,
        string $html,
        ?int $adminUserId
    ): int {
        $name = trim($name);
        if ($name === '' || trim($html) === '' || json_decode($projectData) === null) {
            throw new LocalizedException(__('The design is incomplete: it needs a name and content.'));
        }

        $connection = $this->resource->getConnection();
        $connection->beginTransaction();
        try {
            $existing = $designId ? $this->get($designId) : null;
            /** @var Template $template */
            $template = $this->templateFactory->create();
            if ($existing) {
                $this->templateResource->load($template, (int)$existing['template_id']);
                $baseCode = (string)$existing['base_template_code'];
            }
            $template->setTemplateCode(mb_substr($name, 0, 150))
                ->setTemplateText($html)
                ->setTemplateSubject(mb_substr($subject, 0, 200))
                ->setTemplateType(TemplateTypesInterface::TYPE_HTML)
                ->setOrigTemplateCode($baseCode)
                ->setOrigTemplateVariables(json_encode($this->templateVariables->getDeclared($baseCode)) ?: '{}');
            // Template::beforeSave refuses a name that another template already uses.
            $this->templateResource->save($template);

            $designs = $this->resource->getTableName(self::DESIGN_TABLE);
            $fields = ['project_data' => $projectData, 'mjml' => $mjml];
            if ($existing) {
                $connection->update($designs, $fields, ['design_id = ?' => $designId]);
            } else {
                $connection->insert($designs, $fields + [
                    'template_id' => (int)$template->getId(),
                    'base_template_code' => $baseCode,
                ]);
                $designId = (int)$connection->lastInsertId($designs);
            }

            $connection->insert($this->resource->getTableName(self::VERSION_TABLE), [
                'design_id' => $designId,
                'project_data' => $projectData,
                'mjml' => $mjml,
                'html' => $html,
                'subject' => mb_substr($subject, 0, 200),
                'admin_user_id' => $adminUserId,
            ]);
            $this->pruneVersions($designId);

            $connection->commit();
            return $designId;
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    /** @return array<int, array<string, mixed>> newest first, with the admin's username */
    public function getVersions(int $designId): array
    {
        $connection = $this->resource->getConnection();
        return $connection->fetchAll(
            $connection->select()
                ->from(['version' => $this->resource->getTableName(self::VERSION_TABLE)], ['version_id', 'subject', 'created_at'])
                ->joinLeft(
                    ['user' => $this->resource->getTableName('admin_user')],
                    'user.user_id = version.admin_user_id',
                    ['username']
                )
                ->where('version.design_id = ?', $designId)
                ->order('version.version_id DESC')
        );
    }

    /** Puts an earlier version back; this is saved as a new version, so a restore can itself be undone. */
    public function restore(int $designId, int $versionId, ?int $adminUserId): void
    {
        $design = $this->get($designId);
        $connection = $this->resource->getConnection();
        $version = $connection->fetchRow(
            $connection->select()
                ->from($this->resource->getTableName(self::VERSION_TABLE))
                ->where('version_id = ?', $versionId)
                ->where('design_id = ?', $designId)
        );
        if (!$version) {
            throw new NoSuchEntityException(__('That version of the design no longer exists.'));
        }
        $this->save(
            $designId,
            (string)$design['template_code'],
            (string)$design['base_template_code'],
            (string)$version['subject'],
            (string)$version['project_data'],
            (string)$version['mjml'],
            (string)$version['html'],
            $adminUserId
        );
    }

    private function pruneVersions(int $designId): void
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::VERSION_TABLE);
        $old = $connection->fetchCol(
            $connection->select()->from($table, ['version_id'])
                ->where('design_id = ?', $designId)
                ->order('version_id DESC')
                ->limit(1000, self::KEEP_VERSIONS)
        );
        if ($old) {
            $connection->delete($table, ['version_id IN (?)' => $old]);
        }
    }
}

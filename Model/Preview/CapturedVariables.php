<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Model\Preview;

/** Carries the variables a core sender built, out of the sender, before it sends anything. */
class CapturedVariables extends \RuntimeException
{
    /** @param array<string, mixed> $variables */
    public function __construct(public readonly array $variables)
    {
        parent::__construct('Email variables captured for a preview.');
    }
}

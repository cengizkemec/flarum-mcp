<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Exception;

use RuntimeException;

class McpSkillException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'invalid_skill'
    ) {
        parent::__construct($message);
    }
}

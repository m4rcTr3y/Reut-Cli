<?php
declare(strict_types=1);

namespace Reut\CLI\Commands;

use Reut\CLI\Version;

/**
 * Version command
 */
class VersionCommand extends Command
{
    public function getName(): string
    {
        return 'version';
    }

    public function getDescription(): string
    {
        return 'Show CLI version';
    }

    public function execute(array $args = []): int
    {
        $this->writeln($this->formatter->title('REUT CLI') . ' v' . Version::get());
        return 0;
    }
}



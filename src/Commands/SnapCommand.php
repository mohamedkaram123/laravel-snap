<?php

namespace Mkaram\Snap\Commands;

use Illuminate\Console\Command;

class SnapCommand extends Command
{
    protected $signature = 'snap';

    protected $description = 'Laravel Snap interactive dashboard and help';

    public function handle(): int
    {
        $this->newLine();
        $this->line(' 🚀 <bg=blue;fg=white;options=bold> LARAVEL SNAP </> <fg=gray>v1.0.0</>');
        $this->line('    <fg=gray>Granular pattern and feature snapshotting system.</>');
        $this->newLine();

        $this->comment(' Available Commands:');
        
        $this->line('  <info>snap:pattern</info>   <fg=gray>Extract a feature into a reusable blueprint</>');
        $this->line('  <info>snap:install</info>   <fg=gray>Plant a blueprint into the current application</>');
        $this->line('  <info>snap:list</info>      <fg=gray>View all locally stored blueprints</>');
        
        $this->newLine();
        $this->line(' 💡 <fg=gray>Pro Tip: Append</> <comment>--help</comment> <fg=gray>to any command for detailed options.</>');
        $this->newLine();

        return self::SUCCESS;
    }
}
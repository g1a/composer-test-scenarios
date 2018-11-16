<?php
namespace ComposerTestScenarios;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

trait RunComposer
{
    protected function composer($command, $dir, $args = [])
    {
        $escapedArgs = implode(' ', array_map(function ($item) { return escapeshellarg($item); }, $args));
        $cmd = "composer -n $command --working-dir=$dir " . $escapedArgs;

        exec($cmd . ' 2>&1', $output, $status);
        $output = implode("\n", $output);
        return [$output, $status];
    }
}

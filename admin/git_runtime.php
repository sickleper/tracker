<?php

if (!function_exists('trackerGitEnvCommand')) {
    function trackerGitEnvCommand(string $repoDir, array $extraEnv = []): string
    {
        $gitDir = $repoDir . '/.git';
        $env = array_merge([
            'GIT_CONFIG_COUNT' => '2',
            'GIT_CONFIG_KEY_0' => 'safe.directory',
            'GIT_CONFIG_VALUE_0' => $repoDir,
            'GIT_CONFIG_KEY_1' => 'safe.directory',
            'GIT_CONFIG_VALUE_1' => $gitDir,
        ], $extraEnv);

        $parts = ['env'];
        foreach ($env as $key => $value) {
            $parts[] = $key . '=' . escapeshellarg((string) $value);
        }

        return implode(' ', $parts);
    }
}

if (!function_exists('trackerGitCommand')) {
    function trackerGitCommand(string $repoDir, string $arguments, array $extraEnv = []): string
    {
        return trackerGitEnvCommand($repoDir, $extraEnv)
            . ' git -C ' . escapeshellarg($repoDir) . ' ' . $arguments;
    }
}

if (!function_exists('trackerGitOutput')) {
    function trackerGitOutput(string $repoDir, string $arguments, array $extraEnv = []): string
    {
        return trim((string) shell_exec(trackerGitCommand($repoDir, $arguments, $extraEnv) . ' 2>/dev/null'));
    }
}

if (!function_exists('trackerGitIsConfigured')) {
    function trackerGitIsConfigured(string $repoDir): bool
    {
        return trackerGitOutput($repoDir, 'rev-parse --is-inside-work-tree') === 'true';
    }
}

if (!function_exists('trackerGitSshEnv')) {
    function trackerGitSshEnv(string $repoDir, ?string $preferredHome = null): array
    {
        $preferredHome = trim((string) $preferredHome);
        $sharedRoot = realpath(dirname($repoDir)) ?: dirname($repoDir);

        $candidateConfigs = [];
        $envConfig = trim((string) ($_ENV['TRACKER_GIT_SSH_CONFIG'] ?? getenv('TRACKER_GIT_SSH_CONFIG') ?: ''));
        if ($envConfig !== '') {
            $candidateConfigs[] = $envConfig;
        }
        if ($preferredHome !== '') {
            $candidateConfigs[] = $preferredHome . '/.ssh/config';
        }
        $candidateConfigs[] = $sharedRoot . '/.ssh/config';

        foreach (array_unique($candidateConfigs) as $configPath) {
            if (!is_file($configPath) || !is_readable($configPath)) {
                continue;
            }

            $home = dirname(dirname($configPath));
            return [
                'HOME' => $home,
                'XDG_CONFIG_HOME' => sys_get_temp_dir(),
                'GIT_SSH_COMMAND' => 'ssh -F ' . $configPath,
            ];
        }

        return $preferredHome !== ''
            ? ['HOME' => $preferredHome, 'XDG_CONFIG_HOME' => sys_get_temp_dir()]
            : ['XDG_CONFIG_HOME' => sys_get_temp_dir()];
    }
}

if (!function_exists('trackerGitSshDiagnostics')) {
    function trackerGitSshDiagnostics(string $repoDir, ?string $preferredHome = null): array
    {
        $preferredHome = trim((string) $preferredHome);
        $sharedRoot = realpath(dirname($repoDir)) ?: dirname($repoDir);
        $paths = [];
        if ($preferredHome !== '') {
            $paths[] = $preferredHome . '/.ssh/config';
        }
        $paths[] = $sharedRoot . '/.ssh/config';

        $details = [];
        foreach (array_unique($paths) as $path) {
            $details[] = [
                'path' => $path,
                'exists' => is_file($path),
                'readable' => is_readable($path),
            ];
        }

        return $details;
    }
}

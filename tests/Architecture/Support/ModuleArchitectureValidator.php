<?php

namespace Tests\Architecture\Support;

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Facade;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ModuleArchitectureValidator
{
    public function __construct(private readonly string $root) {}

    public function boundaryViolations(): array
    {
        $violations = [];

        foreach ($this->phpFiles($this->root.'/Modules') as $file) {
            $source = $this->sourceModule($file);

            foreach ($this->moduleReferences($file) as $reference) {
                [$target, $layer] = array_pad(explode('\\', $reference, 3), 3, null);

                if ($target !== $source && $layer !== 'Public') {
                    $violations[] = $this->relative($file).' -> Modules\\'.$reference;
                }
            }
        }

        foreach ($this->phpFiles($this->root.'/app') as $file) {
            foreach ($this->moduleReferences($file) as $reference) {
                $violations[] = $this->relative($file).' -> Modules\\'.$reference;
            }
        }

        sort($violations);

        return array_values(array_unique($violations));
    }

    public function manifestDependencyViolations(): array
    {
        $violations = [];

        foreach ($this->phpFiles($this->root.'/Modules') as $file) {
            $source = $this->sourceModule($file);
            $dependencies = $this->manifestDependencies($source);

            foreach ($this->moduleReferences($file) as $reference) {
                [$target, $layer] = array_pad(explode('\\', $reference, 3), 3, null);

                if ($target !== $source && $layer === 'Public' && ! in_array($target, $dependencies, true)) {
                    $violations[] = $this->relative($file).' -> '.$target.' missing from '.$source.'/module.json dependencies';
                }
            }
        }

        sort($violations);

        return array_values(array_unique($violations));
    }

    public function unguardedRouteViolations(): array
    {
        $violations = [];

        foreach ($this->phpFiles($this->root.'/Modules', '/routes/') as $file) {
            foreach ($this->routesIn($file) as $route) {
                $middleware = (array) ($route->getAction('middleware') ?? []);
                $authenticated = array_filter(
                    $middleware,
                    fn (mixed $item): bool => preg_match('/^auth(?::|$)/', (string) $item) === 1,
                ) !== [];
                $methods = implode(',', $route->methods());
                $key = $this->relative($file).'|'.$methods.'|'.$route->uri();

                if (! $authenticated && ! in_array($key, $this->publicRouteAllowlist(), true)) {
                    $violations[] = sprintf('%s [%s] unguarded -> %s', $this->relative($file), $methods, $route->uri());
                }
            }
        }

        sort($violations);

        return $violations;
    }

    public function httpFileActivatorWriteViolations(): array
    {
        $violations = [];

        foreach (array_merge($this->phpFiles($this->root.'/app'), $this->phpFiles($this->root.'/Modules')) as $file) {
            $relative = $this->relative($file);

            if (str_contains($relative, '/Console/')) {
                continue;
            }

            [$code, $literalText] = $this->normalizedPhp($file);
            $activatorReference = preg_match('/(?:FileActivator|ActivatorInterface)/i', $code) === 1;
            $activationCall = preg_match('/(?:->|::)(?:enable|disable)\(/i', $code) === 1;
            $deploymentPathWrite = str_contains($literalText, 'modulesstatusesjson')
                && preg_match('/(?:file_put_contents|fopen)\(|(?:File|Storage)::(?:put|replace)\(/i', $code) === 1;

            if ($activatorReference || $activationCall || $this->hasContainerResolvedDynamicActivation($code, $literalText) || $deploymentPathWrite) {
                $violations[] = $relative.' -> non-console deployment state mutation';
            }
        }

        sort($violations);

        return array_values(array_unique($violations));
    }

    private function hasContainerResolvedDynamicActivation(string $code, string $literalText): bool
    {
        if (! str_contains($literalText, 'enable') && ! str_contains($literalText, 'disable')) {
            return false;
        }

        preg_match_all(
            '/\$([A-Za-z_][A-Za-z0-9_]*)=(?:(?:app|resolve)\([^;]*activator[^;]*\)|[^;]*(?:->|::)make\([^;]*activator[^;]*\));/i',
            $code,
            $resolutions,
        );

        $activatorVariables = array_fill_keys($resolutions[1], true);
        if ($activatorVariables === []) {
            return false;
        }

        preg_match_all(
            '/\$([A-Za-z_][A-Za-z0-9_]*)=\$([A-Za-z_][A-Za-z0-9_]*);/',
            $code,
            $assignments,
            PREG_SET_ORDER,
        );

        do {
            $addedAlias = false;
            foreach ($assignments as $assignment) {
                if (isset($activatorVariables[$assignment[2]]) && ! isset($activatorVariables[$assignment[1]])) {
                    $activatorVariables[$assignment[1]] = true;
                    $addedAlias = true;
                }
            }
        } while ($addedAlias);

        foreach (array_keys($activatorVariables) as $variable) {
            if (preg_match('/\$'.preg_quote($variable, '/').'->(?:\{[^}]+\}|\$[A-Za-z_][A-Za-z0-9_]*)\(/', $code) === 1) {
                return true;
            }
        }

        return false;
    }

    private function routesIn(string $file): array
    {
        $container = new Container;
        $router = new Router(new Dispatcher($container), $container);
        $container->instance('router', $router);
        Facade::clearResolvedInstance('router');
        Facade::setFacadeApplication($container);

        require $file;

        $routes = iterator_to_array($router->getRoutes());
        Facade::clearResolvedInstance('router');
        Facade::setFacadeApplication(null);

        return $routes;
    }

    private function publicRouteAllowlist(): array
    {
        return [
            'Modules/Users/routes/web.php|GET,HEAD|login',
            'Modules/Users/routes/web.php|POST|login',
            'Modules/Users/routes/web.php|GET,HEAD|register',
            'Modules/Users/routes/web.php|POST|register',
        ];
    }

    private function normalizedPhp(string $file): array
    {
        $code = '';
        $strings = '';

        foreach (token_get_all(file_get_contents($file)) as $token) {
            if (! is_array($token)) {
                $code .= $token;

                continue;
            }

            if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $code .= $token[1];
            if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
                $strings .= stripcslashes(substr($token[1], 1, -1));
            }
        }

        return [$code, preg_replace('/[^a-z0-9]/', '', strtolower($strings))];
    }

    private function moduleReferences(string $file): array
    {
        $references = [];

        foreach (token_get_all(file_get_contents($file)) as $token) {
            if (! is_array($token) || ! in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                continue;
            }

            $name = ltrim($token[1], '\\');

            if (str_starts_with($name, 'Modules\\')) {
                $references[] = substr($name, strlen('Modules\\'));
            }
        }

        return array_values(array_unique($references));
    }

    private function manifestDependencies(string $module): array
    {
        $manifest = $this->root.'/Modules/'.$module.'/module.json';
        $data = is_file($manifest) ? json_decode(file_get_contents($manifest), true) : null;

        return is_array($data) && is_array($data['dependencies'] ?? null)
            ? array_values($data['dependencies'])
            : [];
    }

    private function sourceModule(string $file): string
    {
        $segments = explode('/', $this->relative($file));

        return $segments[0] === 'Modules' ? ($segments[1] ?? '') : '';
    }

    private function phpFiles(string $directory, ?string $pathFragment = null): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            $path = $file->getPathname();

            $normalizedPath = str_replace('\\', '/', $path);

            if ($file->isFile() && $file->getExtension() === 'php' && ($pathFragment === null || str_contains($normalizedPath, $pathFragment))) {
                $files[] = $path;
            }
        }

        sort($files);

        return $files;
    }

    private function relative(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen($this->root) + 1));
    }
}

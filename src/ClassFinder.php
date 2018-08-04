<?php
namespace HaydenPierce\ClassFinder;

class ClassFinder
{
    public static $appRoot;

    /**
     * @throws \Exception
     */
    private static function findAppRoot()
    {
        if (self::$appRoot) {
            return self::$appRoot;
        }

        $workingDirectory = str_replace('\\', '/', __DIR__);
        $workingDirectory = str_replace('/vendor/haydenpierce/class-finder/src', '', $workingDirectory);
        $directoryPathPieces = explode('/', $workingDirectory);

        $appRoot = null;
        do {
            $path = implode('/', $directoryPathPieces) . '/composer.json';
            if (file_exists($path)) {
                $appRoot = implode('/', $directoryPathPieces) . '/';
            } else {
                array_pop($directoryPathPieces);
            }
        } while (is_null($appRoot) && count($directoryPathPieces) > 0);

        if (is_null($appRoot)) {
            throw new \Exception('Could not locate composer.json.');
        } else {
            return $appRoot;
        }
    }

    /**
     * @param $namespace
     * @return array
     * @throws \Exception
     */
    public static function getClassesInNamespace($namespace)
    {
        $files = scandir(self::getNamespaceDirectory($namespace));

        $classes = array_map(function($file) use ($namespace){
            return $namespace . '\\' . str_replace('.php', '', $file);
        }, $files);

        $classes = array_filter($classes, function($possibleClass){
            return class_exists($possibleClass);
        });

        return array_values($classes);
    }

    /**
     * @return array
     * @throws \Exception
     */
    private static function getDefinedNamespaces()
    {
        $appRoot = self::findAppRoot();

        $composerJsonPath = $appRoot. 'composer.json';
        $composerConfig = json_decode(file_get_contents($composerJsonPath));

        //Apparently PHP doesn't like hyphens, so we use variable variables instead.
        $psr4 = "psr-4";
        return (array) $composerConfig->autoload->$psr4;
    }

    /**
     * @param $namespace
     * @return bool|string
     * @throws \Exception
     */
    private static function getNamespaceDirectory($namespace)
    {
        $appRoot = self::findAppRoot();

        $composerNamespaces = self::getDefinedNamespaces();

        $namespaceFragments = explode('\\', $namespace);
        $undefinedNamespaceFragments = [];

        while($namespaceFragments) {
            $possibleNamespace = implode('\\', $namespaceFragments) . '\\';

            if(array_key_exists($possibleNamespace, $composerNamespaces)){
                return realpath($appRoot . $composerNamespaces[$possibleNamespace] . implode('/', $undefinedNamespaceFragments));
            }

            array_unshift($undefinedNamespaceFragments, array_pop($namespaceFragments));
        }

        throw new ClassFinderException(sprintf("Unknown namespace '%s'. You should add the namespace prefix to composer.json. See '%s' for details.",
            $namespace,
            'https://gitlab.com/hpierce1102/ClassFinder' // TODO: write documentation and update this link.
        ));
    }
}
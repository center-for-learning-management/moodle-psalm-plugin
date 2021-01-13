<?php
namespace Klebann\MoodlePsalmPlugin;

use Klebann\MoodlePsalmPlugin\Hooks\MoodleScanner;
use Klebann\MoodlePsalmPlugin\Hooks\MyMethodReturnTypeProvider;
use SimpleXMLElement;
use Psalm\Plugin\PluginEntryPointInterface;
use Psalm\Plugin\RegistrationInterface;

class Plugin implements PluginEntryPointInterface
{
    public function __invoke(RegistrationInterface $psalm, ?SimpleXMLElement $config = null): void
    {
        if(class_exists(MoodleScanner::class)){
            $psalm->registerHooksFromClass(MoodleScanner::class);
        }

        if(class_exists(MyMethodReturnTypeProvider::class)){
            $psalm->registerHooksFromClass(MyMethodReturnTypeProvider::class);
        }
    }
}

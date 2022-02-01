<?php

declare(strict_types=1);

namespace CoralMedia\Bundle\FrameworkBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{

    public function getConfigTreeBuilder(): TreeBuilder
    {
        return new TreeBuilder('coral_media_framework');
    }
}
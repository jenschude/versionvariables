<?php

namespace Jenschude\VersionVariables;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\BasePackage;
use Composer\Package\Link;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Semver\VersionParser;

class VersionVariablesPlugin implements PluginInterface, EventSubscriberInterface
{
    public function activate(Composer $composer, IOInterface $io)
    {
    }

    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::PRE_INSTALL_CMD => 'onPreDependenciesSolving',
            ScriptEvents::PRE_UPDATE_CMD => 'onPreDependenciesSolving'
        ];
    }

    public static function onPreDependenciesSolving(Event $event)
    {
        $requires = $event->getComposer()->getPackage()->getRequires();
        $extra = $event->getComposer()->getPackage()->getExtra();
        $parser = new VersionParser();
        $updatedRequires = [];
        $shortRequires = [];
        foreach ($requires as $require) {
            if (preg_match("/\+(:[a-z]+)/", $require->getPrettyConstraint(), $matches) != false) {
                $replaceConstraint = $extra['versionvariables'][$matches[1]];
                $constraint = $parser->parseConstraints($replaceConstraint);
                $require = new Link($require->getSource(), $require->getTarget(), $constraint, $require->getDescription(), $replaceConstraint);
            }
            $shortRequires[$require->getTarget()] = $require->getPrettyConstraint();
            $updatedRequires[] = $require;
        }
        $event->getComposer()->getPackage()->setRequires($updatedRequires);
        $stabilityFlags = $event->getComposer()->getPackage()->getStabilityFlags();
        $event->getComposer()->getPackage()->getStability();
        $newStabilityFlags = self::extractStabilityFlags($shortRequires, $stabilityFlags, $event->getComposer()->getPackage()->getMinimumStability());
        $event->getComposer()->getPackage()->setStabilityFlags($newStabilityFlags);
    }

    private static function extractStabilityFlags(array $requires, array $stabilityFlags, $minimumStability)
    {
        $stabilities = BasePackage::$stabilities;
        $minimumStability = $stabilities[$minimumStability];
        foreach ($requires as $reqName => $reqVersion) {
            $constraints = array();

            // extract all sub-constraints in case it is an OR/AND multi-constraint
            $orSplit = preg_split('{\s*\|\|?\s*}', trim($reqVersion));
            foreach ($orSplit as $orConstraint) {
                $andSplit = preg_split('{(?<!^|as|[=>< ,]) *(?<!-)[, ](?!-) *(?!,|as|$)}', $orConstraint);
                foreach ($andSplit as $andConstraint) {
                    $constraints[] = $andConstraint;
                }
            }

            // parse explicit stability flags to the most unstable
            $match = false;
            foreach ($constraints as $constraint) {
                if (preg_match('{^[^@]*?@('.implode('|', array_keys($stabilities)).')$}i', $constraint, $match)) {
                    $name = strtolower($reqName);
                    $stability = $stabilities[\Composer\Package\Version\VersionParser::normalizeStability($match[1])];

                    if (isset($stabilityFlags[$name]) && $stabilityFlags[$name] > $stability) {
                        continue;
                    }
                    $stabilityFlags[$name] = $stability;
                    $match = true;
                }
            }

            if ($match) {
                continue;
            }

            foreach ($constraints as $constraint) {
                // infer flags for requirements that have an explicit -dev or -beta version specified but only
                // for those that are more unstable than the minimumStability or existing flags
                $reqVersion = preg_replace('{^([^,\s@]+) as .+$}', '$1', $constraint);
                if (preg_match('{^[^,\s@]+$}', $reqVersion) && 'stable' !== ($stabilityName = VersionParser::parseStability($reqVersion))) {
                    $name = strtolower($reqName);
                    $stability = $stabilities[$stabilityName];
                    if ((isset($stabilityFlags[$name]) && $stabilityFlags[$name] > $stability) || ($minimumStability > $stability)) {
                        continue;
                    }
                    $stabilityFlags[$name] = $stability;
                }
            }
        }

        return $stabilityFlags;
    }
}

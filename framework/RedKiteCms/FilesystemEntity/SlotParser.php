<?php
/**
 * This file is part of the RedKite CMS Application and it is distributed
 * under the GPL LICENSE Version 2.0. To use this application you must leave
 * intact this copyright notice.
 *
 * Copyright (c) RedKite Labs <info@redkite-labs.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * For extra documentation and help please visit http://www.redkite-labs.com
 *
 * @license    GPL LICENSE Version 2.0
 *
 */

namespace RedKiteCms\FilesystemEntity;


use JMS\Serializer\SerializerInterface;
use RedKiteCms\Tools\FilesystemTools;
use RedKiteCms\Tools\JsonTools;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class SlotParser
{
    private $serializer;
    private $isProduction = false;

    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    public function fetchBlocks($productionDir, $slotDir, $slotName)
    {
        $slot = array(
            'next' => '1',
            'blocks' => array(),
        );
        if (null === $slotDir) {
            return $slot;
        }

        // Copies the active slot for a new contributor
        if (!is_dir($slotDir)) {
            $filesystem = new Filesystem();
            $filesystem->mirror($productionDir, $slotDir);
        }

        $file = $slotDir . '/slot.json';
        if (!file_exists($file)) {
            return $slot;
        }

        $this->isProduction = $productionDir === $slotDir;

        $found = array();
        $blocks = json_decode(FilesystemTools::readFile($file), true);
        foreach ($blocks["blocks"] as $blockName) {
            $block = $this->fetchBlock($slotDir, $slotName, $blockName);
            if (null === $block) {
                continue;
            }
            $found[] = $block;
        }

        return array(
            'next' => $blocks["next"],
            'blocks' => $found,
        );
    }

    public function fetchBlock($slotDir, $slotName, $blockName)
    {
        $filename = $slotDir . '/blocks/' . $blockName . '.json';
        if (!file_exists($filename)) {
            return null;
        }

        if ($this->isProduction) {
            return file_get_contents($filename);
        }

        $block = $this->updateBlock($filename, $slotName, $blockName);
        if (null === $block) {
            return null;
        }

        $block->setHistory($this->fetchArchivedBlocks($slotDir . '/archive/' . $blockName, $slotName));

        return JsonTools::toJson($this->serializer, $block);
    }

    private function updateBlock($file, $slotName, $blockName)
    {
        $json = FilesystemTools::readFile($file);
        $block = JsonTools::toBlock($this->serializer, $json);
        if (null === $block) {
            return null;
        }

        $block->setName($blockName);
        $block->setSlotName($slotName);

        return $block;
    }

    public function fetchArchivedBlocks($archiveDir, $slotName)
    {
        if (!is_dir($archiveDir)) {
            return array();
        }

        $found = array();
        $blockName = basename($archiveDir);

        // Backward compatibility
        $this->updateHistory($archiveDir);

        $historyFile = $archiveDir . '/history.json';
        $history = json_decode(file_get_contents($historyFile), true);
        foreach ($history as $name => $block) {
            $block = JsonTools::toBlock($this->serializer, json_encode($block));
            if (null === $block) {
                continue;
            }

            $block->setName($blockName);
            $block->setSlotName($slotName);
            $block->setHistoryName($name);
            $found[$name] = $block;
        }
        krsort($found);
        $result = array_values($found);

        return $result;
    }

    private function updateHistory($archiveDir)
    {
        $historyFile = $archiveDir . '/history.json';
        if (file_exists($historyFile)) {
            return;
        }

        $finder = new Finder();
        $files = $finder->files()->depth(0)->in($archiveDir);

        $history = array();
        foreach ($files as $file) {
            $name = basename($file, '.json');
            $history[$name] = json_decode(file_get_contents($file), true);
            unlink($file);
        }

        file_put_contents($historyFile, json_encode($history));
    }
} 
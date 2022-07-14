<?php

/*
 * This file is part of the Access to Memory (AtoM) software.
 *
 * Access to Memory (AtoM) is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Access to Memory (AtoM) is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Access to Memory (AtoM).  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Bulk export term data as CSV.
 *
 * @author  Mike Cantelon <mike@artefactual.com>
 */
class csvExportDigitalObjectTask extends exportBulkBaseTask
{
    protected $namespace = 'csv';
    protected $name = 'digitalobject-export';
    protected $briefDescription = 'Export digital object data as CSV file(s)';

    /**
     * @see sfTask
     *
     * @param mixed $arguments
     * @param mixed $options
     */
    public function execute($arguments = [], $options = [])
    {
        if (isset($options['items-until-update']) && !ctype_digit($options['items-until-update'])) {
            throw new UnexpectedValueException('items-until-update must be a number');
        }

        $configuration = ProjectConfiguration::getApplicationConfiguration('qubit', 'cli', false);
        $this->context = sfContext::createInstance($configuration);
        $conn = $this->getDatabaseConnection();

        $this->checkPathIsWritable($arguments['path']);

        $this->exportDigitalObjectsToCsv($arguments['path'], $options);

        /*
        $itemsExported = $this->exportToCsv($arguments['path'], $options);

        if ($itemsExported) {
            $this->log(sprintf("\nExport complete (%d terms exported).", $itemsExported));
        } else {
            $this->log('No terms found to export.');
        }
        */
    }

    /**
     * @see sfTask
     */
    protected function configure()
    {
        $this->addCoreArgumentsAndOptions();

        $this->addOptions([
            new sfCommandOption('single-slug', null, sfCommandOption::PARAMETER_OPTIONAL, 'Export terms related to a single fonds or collection based on slug'),
        ]);
    }

    /**
     * Handle export of digital object data.
     *
     * @param string $exportPath path to export to (file or directory)
     * @param mixed  $options    export options
     *
     * @return int number of items items exported
     */
    private function exportDigitalObjectsToCsv($exportPath, $options)
    {
        // Handle option to export a single hierarchy or description's digital
        // objects only
        if (!empty($slug = $options['single-slug'])) {
            // Determine IDS of terms in hierarchy or for a single description
            $digitalObjectIds = $this->getDigitalObjectIdsInHierarchy(
                $slug,
                empty($options['current-level-only'])
            );

            // Write export and return number of digital objects exported
            return $this->writeExport($exportPath, $options, $digitalObjectIds);
        }

        // Write export and return number of digital objects exported
        return $this->writeExport($exportPath, $options);
    }

    private function getDigitalObjectIdsInHierarchy($slug, $currentLevelOnly = false)
    {
        $digitalObjectIds = [];

        $io = QubitInformationObject::getBySlug($slug);

        if (null === $io)
        {
            throw new sfException('No information object found with that slug.');
        }

        $digitalObjectIds[] = $io->digitalObjectsRelatedByobjectId[0]->id;

        foreach ($io->getDescendantsForExport() as $descendant) {
            if (!empty($descendant->digitalObjectsRelatedByobjectId[0]))
            {
                $digitalObjectIds[] = $descendant->digitalObjectsRelatedByobjectId[0]->id;
            }
        }

        return $digitalObjectIds;
    }

    private function writeExport($exportPath, $options, $digitalObjectIds = null)
    {
        // Create directory to put files into
        $fileSubDir = $exportPath .'/files';

        if (!is_dir($fileSubDir))
        {
            mkdir($fileSubDir);
        }

        // Open CSV file and add header row
        $fp = fopen($exportPath .'/digital_objects.csv', 'w');
        fputcsv($fp, ['slug', 'filename']);

        if (!empty($digitalObjectIds))
        {
            // Export select digital objects
            foreach ($digitalObjectIds as $doId)
            {
                $do = QubitDigitalObject::getById($doId);
                $this->exportDigitalObject($fileSubDir, $fp, $do);
            }
        }
        else
        {
            // Export all master digital objects
            $criteria = new Criteria;

            $criteria->add(QubitDigitalObject::USAGE_ID, QubitTerm::MASTER_ID);

            foreach (QubitDigitalObject::get($criteria) as $do)
            {
                $this->exportDigitalObject($fileSubDir, $fp, $do);
            }
        }

        fclose($fp);
    }



    /**
     * Handle export of digital object data.
     *
     * @param string $destDir        directory to export to
     * @param string $hierarchySlug  slug of hierarchy to export from
     *
     * @return int number of items items exported
     */






    private function exportDigitalObject($fileSubDir, $fp, $do, $io = null)
    {
        if (empty($io))
        {
            $io = QubitInformationObject::getById($do->objectId);
        }

        if (null !== $io)
        {
            // Assemble destination relative path based on digital object path
            $relativeFilePath = ltrim(ltrim($do->path . $do->name), '/');
            $newRelPath = $fileSubDir .'/'. $do->id;
            $newRelFilePath = $newRelPath .'/'. $do->name;

            // Create destination relative path
            if (!is_dir($newRelPath))
            {
                mkdir($newRelPath);
            }

            // Copy master file to export directory and add row to CSV
            copy($relativeFilePath, $newRelFilePath);

            fputcsv($fp, [$io->slug, $newRelFilePath]);
        }
    }
}

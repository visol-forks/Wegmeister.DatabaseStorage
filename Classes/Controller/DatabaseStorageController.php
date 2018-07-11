<?php
namespace Wegmeister\DatabaseStorage\Controller;

/**
 * This file is part of the RadKultur.Wettbewerb package.
 */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Mvc\Controller\ActionController;
use TYPO3\Flow\Resource\ResourceManager;
use TYPO3\Flow\Resource\Resource;

use Wegmeister\DatabaseStorage\Domain\Repository\DatabaseStorageRepository;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Exception as WriterException;

/**
 * The Database Storage controller
 *
 * @Flow\Scope("singleton")
 */
class DatabaseStorageController extends ActionController
{
    /**
     * Array with extension and mime type for spreadsheet writers.
     * @var array
     */
    protected static $types = [
        'Xls' => [
            'extension' => 'xls',
            'mimeType'  => 'application/vnd.ms-excel',
        ],
        'Xlsx' => [
            'extension' => 'xlsx',
            'mimeType'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
        'Ods' => [
            'extension' => 'ods',
            'mimeType'  => 'application/vnd.oasis.opendocument.spreadsheet',
        ],
        'Csv' => [
            'extension' => 'csv',
            'mimeType'  => 'text/csv',
        ],
        'Html' => [
            'extension' => 'html',
            'mimeType'  => 'text/html',
        ],
    ];

    /**
     * @Flow\Inject
     * @var DatabaseStorageRepository
     */
    protected $databaseStorageRepository;

    /**
     * @Flow\Inject
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * @var array
     */
    protected $settings;


    /**
     * Inject the settings
     *
     * @param array $settings
     * @return void
     */
    public function injectSettings(array $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Show list of identifiers
     *
     * @return void
     */
    public function indexAction()
    {
        $this->view->assign('identifiers', $this->databaseStorageRepository->findStorageidentifiers());
    }


    /**
     * Delete all entries for the given identifier.
     *
     * @param string $identifier
     * @param bool $redirect
     * @return void
     */
    public function deleteAllAction($identifier, $redirect = false)
    {
        $count = 0;
        foreach ($this->databaseStorageRepository->findByStorageidentifier($identifier) as $entry) {
            $this->databaseStorageRepository->remove($entry);
            $count++;
        }

        $this->view->assign('identifier', $identifier);
        $this->view->assign('count', $count);

        if ($redirect) {
            $this->addFlashMessage('Teilnehmer erfolgreich entfernt.');
            $this->redirect('index');
        }
    }


    /**
     * Export all entries for a specific identifier as xls.
     *
     * @param string $identifier
     * @param string $writerType
     *
     * @return void
     */
    public function exportAction($identifier, $writerType = 'Xlsx')
    {
        if (!isset(self::$types[$writerType])) {
            throw new WriterException('No writer available for type ' . $writerType . '.', 1521787983);
        }

        $entries = $this->databaseStorageRepository->findByStorageidentifier($identifier)->toArray();

        $dataArray = [];

        $spreadsheet = new Spreadsheet();

        $spreadsheet->getProperties()
            ->setCreator($this->settings['creator'])
            ->setTitle($this->settings['title'])
            ->setSubject($this->settings['subject']);

        $spreadsheet->setActiveSheetIndex(0);
        $spreadsheet->getActiveSheet()->setTitle($this->settings['title']);

        $titles = [];
        $columns = 0;
        foreach ($entries[0]->getProperties() as $title => $value) {
            $titles[] = $title;
            $columns++;
        }

        $dataArray[] = $titles;


        foreach ($entries as $entry) {
            $values = [];

            foreach ($entry->getProperties() as $value) {
                if ($value instanceof Resource) {
                    $values[] = $this->resourceManager->getPublicPersistentResourceUri($value) ?: '-';
                } elseif (is_string($value)) {
                    $values[] = $value;
                } elseif (is_object($value) && method_exists($value, '__toString')) {
                    $values[] = (string)$value;
                }
            }

            $dataArray[] = $values;
        }

        $spreadsheet->getActiveSheet()->fromArray($dataArray);

        // TODO: Set headline bold
        $prefixIndex = 64;
        $prefixKey = '';
        for ($i = 0; $i < $columns; $i++) {
            $index = $i % 26;
            $columnStyle = $spreadsheet->getActiveSheet()->getStyle($prefixKey . chr(65 + $index) . '1');
            $columnStyle->getFont()->setBold(true);
            $columnStyle->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);

            if ($index + 1 > 25) {
                $prefixIndex++;
                $prefixKey = chr($prefixIndex);
            }
        }


        if (ini_get('zlib.output_compression')) {
            ini_set('zlib.output_compression', 'Off');
        }

        header("Pragma: public"); // required
        header("Expires: 0");
        header('Cache-Control: max-age=0');
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Cache-Control: private", false); // required for certain browsers
        header('Content-Type: ' . self::$types[$writerType]['mimeType']);
        header(sprintf(
            'Content-Disposition: attachment; filename="Database-Storage-%s.%s"',
            $identifier,
            self::$types[$writerType]['extension']
        ));
        header("Content-Transfer-Encoding: binary");

        $writer = IOFactory::createWriter($spreadsheet, $writerType);
        $writer->save('php://output');
        exit;
    }
}

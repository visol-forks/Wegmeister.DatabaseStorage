<?php
namespace Wegmeister\DatabaseStorage\Domain\Repository;

/**
 * This file is part of the Wegmeister.DatabaseStorage package.
 */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Persistence\Repository;
use TYPO3\Flow\Persistence\QueryInterface;

/**
 * @Flow\Scope("singleton")
 */
class DatabaseStorageRepository extends Repository
{

    /**
     * @var array
     */
    protected $defaultOrderings = [
        'storageidentifier' => QueryInterface::ORDER_ASCENDING,
        'datetime'   => QueryInterface::ORDER_DESCENDING
    ];

    /**
     * @var string
     */
    protected $currentIdentifier = false;

    /**
     * @var array
     */
    protected $identifiers = [];


    /**
     * Find all identifiers.
     *
     * @return mixed
     */
    public function findStorageidentifiers()
    {
        if ($this->identifiers === []) {
            foreach ($this->findAll() as $item) {
                if ($this->currentIdentifier !== $item->getStorageidentifier()) {
                    $this->identifiers[] = $item->getStorageidentifier();
                    $this->currentIdentifier = $item->getStorageidentifier();
                }
            }
        }

        return $this->identifiers;
    }
}

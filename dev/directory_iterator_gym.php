<?php
/**
 * Class MyRecursiveFilterIterator
 */
class DirectoryRecursiveFilterIterator extends RecursiveFilterIterator
{
    /**
     * @var bool
     */
    public $includeHidden;

    /**
     * @var array
     */
    public $fileExtensions;

    /**
     * @param RecursiveDirectoryIterator $iterator
     * @param array $fileExtensions
     * @param bool $includeHidden
     */
    public function __construct(
        RecursiveDirectoryIterator $iterator, array $fileExtensions = [], bool $includeHidden = false
    )
    {
        $this->fileExtensions = $fileExtensions;
        $this->includeHidden = $includeHidden;
        parent::__construct($iterator);
    }

    /**
     *
     */
    public function getChildren()
    {
        return new self($this->getInnerIterator()->getChildren(), $this->fileExtensions, $this->includeHidden);
    }

    /**
     * Filters.
     *
     * @return bool
     */
    public function accept() : bool
    {
        $current = $this->current();

        if (!$this->includeHidden && $current->getFilename(){0} == '.') {
            return false;
        }

        if ($this->fileExtensions && $current->isFile() && !in_array($current->getExtension(), $this->fileExtensions)) {
            return false;
        }
        return true;
    }
}

/**
 * Class FileFilterIterator
 */
class FileFilterIterator extends FilterIterator
{
    /**
     * Filters.
     *
     * @return bool
     */
    public function accept() : bool
    {
        return $this->current()->isFile();
    }
}

// http://php.net/manual/en/class.recursivefilteriterator.php

$path = '/www/00_simplecomplex/sites/php-psr.source/conf/ini';

/**
 *
 */
function rubbish_directory_whatever($path, $depth = 0)
{
    if ($depth > 1) {
        return [];
    }

    $iterator = new RecursiveDirectoryIterator(
        $path,
        FilesystemIterator::UNIX_PATHS | FilesystemIterator::FOLLOW_SYMLINKS | FilesystemIterator::SKIP_DOTS
    );
    $net = [];
    foreach ($iterator as $item) {
        if ($item->getFilename(){0} == '.') {
            continue;
        }
        if ($item->isDir()) {
            $net = array_merge(
                $net,
                rubbish_directory_whatever($item->getPathName(), $depth + 1)
            );
        } elseif ($item->getExtension() == 'ini') {
            $net[] = $item->getPathName();
        }
    }
    return $net;
}
$arr = rubbish_directory_whatever($path);
foreach ($arr as $file) {
    echo $file . "\n";
}
exit;

$iterator = new RecursiveIteratorIterator(
    new DirectoryRecursiveFilterIterator(
        new RecursiveDirectoryIterator(
            $path,
            FilesystemIterator::UNIX_PATHS | FilesystemIterator::FOLLOW_SYMLINKS | FilesystemIterator::SKIP_DOTS
        ),
        [
            'ini',
        ],
        false
    ),
    RecursiveIteratorIterator::SELF_FIRST
);
$iterator->setMaxDepth(10);
$iterator = new FileFilterIterator($iterator);
foreach ($iterator as $item) {
    $name = $item->getFilename();
    /* if ($name{0} === '.') {
         continue;
     }*/

    echo $item->getPath() . '/' . $name . "\n";
}
exit;
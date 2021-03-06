<?php
namespace Omeka\File\Thumbnailer;

use Omeka\File\Exception;
use Omeka\File\File;
use Omeka\File\Manager as FileManager;

class GdThumbnailer extends AbstractThumbnailer
{
    /**
     * @var resource
     */
    protected $origImage;

    /**
     * @var int The width of the original image
     */
    protected $origWidth;

    /**
     * @var int The height of the original image
     */
    protected $origHeight;

    /**
     * Check whether the GD entension is loaded.
     *
     * @throws Exception\InvalidThumbnailer
     */
    public function __construct()
    {
        if (!extension_loaded('gd')) {
            throw new Exception\InvalidThumbnailerException;
        }
    }

    /**
     * Create image resource.
     *
     * {@inheritDoc}
     */
    public function setSource(File $source)
    {
        $mediaType = $source->getMediaType();
        $sourcePath = $source->getTempPath();

        switch ($mediaType) {
            case 'image/gif':
                $origImage = imagecreatefromgif($sourcePath);
                break;
            case 'image/jpeg':
                $origImage = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $origImage = imagecreatefrompng($sourcePath);
                break;
            case 'image/webp':
                $origImage = imagecreatefromwebp($sourcePath);
                break;
            default:
                throw new Exception\CannotCreateThumbnailException(
                    sprintf('Cannot create thumbnail for type "%s"', $mediaType)
                );
        }
        if (false === $origImage) {
            throw new Exception\CannotCreateThumbnailException;
        }
        $this->origImage = $origImage;
        $this->origWidth = imagesx($origImage);
        $this->origHeight = imagesy($origImage);
    }

    /**
     * {@inheritDoc}
     */
    public function create(FileManager $fileManager, $strategy, $constraint, array $options = [])
    {
        switch ($strategy) {
            case 'square':
                $tempImage = $this->createSquare($constraint, $options);
                break;
            case 'default':
            default:
                $tempImage = $this->createDefault($constraint, $options);
        }

        // Save a temporary thumbnail image.
        $file = $fileManager->getTempFile();
        $saveResult = imagejpeg($tempImage, $file->getTempPath());

        if (false === $saveResult) {
            imagedestroy($tempImage);
            throw new Exception\CannotCreateThumbnailException;
        }

        imagedestroy($tempImage);
        return $file->getTempPath();
    }

    /**
     * Create a default thumbnail.
     *
     * @param int $constraint
     * @param array $options
     * @return resource A "gd" resource
     */
    public function createDefault($constraint, array $options)
    {
        // Original is smaller than constraint
        if ($this->origWidth < $constraint && $this->origHeight < $constraint) {
            $tempWidth = $this->origWidth;
            $tempHeight = $this->origHeight;
        // Original is landscape
        } elseif ($this->origWidth > $this->origHeight) {
            $tempWidth = $constraint;
            $tempHeight = round($this->origHeight * $constraint / $this->origWidth);
        }
        // Original is portrait
        elseif ($this->origWidth < $this->origHeight) {
            $tempWidth = round($this->origWidth * $constraint / $this->origHeight);
            $tempHeight = $constraint;
        }
        // Original is square
        else {
            $tempWidth = $constraint;
            $tempHeight = $constraint;
        }

        $tempImage = $this->createTempImage($tempWidth, $tempHeight);
        $resizeResult = imagecopyresampled($tempImage, $this->origImage, 0, 0,
            0, 0, $tempWidth, $tempHeight, $this->origWidth, $this->origHeight);

        if (false === $resizeResult) {
            imagedestroy($tempImage);
            throw new Exception\CannotCreateThumbnailException;
        }

        return $tempImage;
    }

    /**
     * Create a square thumbnail.
     *
     * @param int $constraint
     * @param array $options
     * @return resource A "gd" resource
     */
    public function createSquare($constraint, array $options)
    {
        $gravity = isset($options['gravity']) ? $options['gravity'] : 'center';

        // Original is landscape
        if ($this->origWidth > $this->origHeight) {
            $origSize = $this->origHeight;
            $origX = $this->getOffsetX($this->origWidth, $origSize, $gravity);
            $origY = 0;
        }
        // Original is portrait
        elseif ($this->origWidth < $this->origHeight) {
            $origSize = $this->origWidth;
            $origX = 0;
            $origY = $this->getOffsetY($this->origHeight, $origSize, $gravity);
        }
        // Original is square
        else {
            $origSize = $this->origWidth;
            $origX = 0;
            $origY = 0;
        }

        $tempImage = $this->createTempImage($constraint, $constraint);
        $resizeResult = imagecopyresampled($tempImage, $this->origImage, 0, 0,
            $origX, $origY, $constraint, $constraint, $origSize, $origSize);

        if (false === $resizeResult) {
            imagedestroy($tempImage);
            throw new Exception\CannotCreateThumbnailException;
        }

        return $tempImage;
    }

    /**
     * Create a temporary thumbnail image.
     *
     * @param int $width
     * @param int $height
     * @return resource
     */
    public function createTempImage($width, $height)
    {
        $tempImage = imagecreatetruecolor($width, $height);

        // Replace transparent parts of the image with white instead of black.
        $white = imagecolorallocate($tempImage, 255, 255, 255);
        imagefill($tempImage, 0, 0, $white);

        return $tempImage;
    }

    /**
     * Destroy the GD resource.
     *
     * This works because the gd thumbnailer is a non-shared service.
     */
    public function __destruct()
    {
        if (is_resource($this->origImage)) {
            imagedestroy($this->origImage);
        }
    }
}

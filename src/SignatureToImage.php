<?php

namespace VKR\SignatureToImageNew;

class SignatureToImage
{
    /* Dimensions of resulting image */

    /** @var int */
    private $imageWidth = 198;

    /** @var int */
    private $imageHeight = 55;

    /* Colors of background */

    /** @var int */
    private $backgroundRed = 255;

    /** @var int */
    private $backgroundGreen = 255;

    /** @var int */
    private $backgroundBlue = 255;

    /* Colors of pen */

    /** @var int */
    private $penRed = 20;

    /** @var int */
    private $penGreen = 83;

    /** @var int */
    private $penBlue = 148;

    /** @var bool True if the background is transparent */
    private $hasTransparentBackground = false;

    /** @var int Width of pen for signature */
    private $penWidth = 2;

    /** @var int Increases temporary image size for draw precision */
    private $drawMultiplier = 12;

    /** @var resource|null Temporary image used for drawing */
    private $temporaryImage;

    /**
     * @param int $width
     * @param int $height
     */
    public function setImageSize(int $width, int $height): void
    {
        $this->imageWidth = $width;
        $this->imageHeight = $height;
    }

    /**
     * @param int $red
     * @param int $green
     * @param int $blue
     */
    public function setBackgroundColor(int $red, int $green, int $blue): void
    {
        $this->backgroundRed = $red;
        $this->backgroundGreen = $green;
        $this->backgroundBlue = $blue;
    }

    /**
     * @param int $red
     * @param int $green
     * @param int $blue
     */
    public function setPenColor(int $red, int $green, int $blue): void
    {
        $this->penRed = $red;
        $this->penGreen = $green;
        $this->penBlue = $blue;
    }

    public function setTransparentBackground(): void
    {
        $this->hasTransparentBackground = true;
    }

    /**
     * @param int $width
     */
    public function setPenWidth(int $width): void
    {
        $this->penWidth = $width;
    }

    /**
     * @param int $multiplier
     */
    public function setDrawMultiplier(int $multiplier): void
    {
        $this->drawMultiplier = $multiplier;
    }

    /**
     * @return int
     */
    private function getTemporaryWidth(): int
    {
        return $this->imageWidth * $this->drawMultiplier;
    }

    /**
     * @return int
     */
    private function getTemporaryHeight(): int
    {
        return $this->imageHeight * $this->drawMultiplier;
    }

    /**
     * @return int
     */
    private function getTemporaryPenWidth(): int
    {
        return $this->penWidth * ($this->drawMultiplier / 2);
    }

    /**
     * @param string $json JSON representation of signature as array of objects representing lines, where each object has keys:
     *      'lx' - starting X of line
     *      'ly' - starting Y of line
     *      'mx' - ending X of line
     *      'my' - ending Y of line
     * @return resource
     */
    public function formImageFromSignature(string $json)
    {
        $this->temporaryImage = imagecreatetruecolor($this->getTemporaryWidth(), $this->getTemporaryHeight());

        $background = $this->allocateColor();

        $penColor = imagecolorallocate($this->temporaryImage, $this->penRed, $this->penGreen, $this->penBlue);
        imagefill($this->temporaryImage, 0, 0, $background);

        $lines = $this->initializeJsonData($json);

        foreach ($lines as $line) {
            $startX = $line->startX * $this->drawMultiplier;
            $startY = $line->startY * $this->drawMultiplier;
            $endX = $line->endX * $this->drawMultiplier;
            $endY = $line->endY * $this->drawMultiplier;
            $this->drawThickLine(
                $this->temporaryImage,
                $startX,
                $startY,
                $endX,
                $endY,
                $penColor,
                $this->getTemporaryPenWidth()
            );
        }

        $destinationImage = imagecreatetruecolor($this->imageWidth, $this->imageHeight);

        if ($this->hasTransparentBackground) {
            imagealphablending($destinationImage, false);
            imagesavealpha($destinationImage, true);
        }

        imagecopyresampled(
            $destinationImage,
            $this->temporaryImage,
            0,
            0,
            0,
            0,
            $this->imageWidth,
            $this->imageHeight,
            $this->getTemporaryWidth(),
            $this->getTemporaryHeight()
        );
        imagedestroy($this->temporaryImage);

        return $destinationImage;
    }

    /**
     * @param string $json
     * @return LineCoordinates[]
     */
    private function initializeJsonData(string $json): array
    {
        $jsonArray = json_decode(stripslashes($json), true);
        $lines = [];
        foreach ($jsonArray as $row) {
            $line = new LineCoordinates();
            if (array_key_exists('lx', $row)) {
                $line->startX = $row['lx'];
            }
            if (array_key_exists('ly', $row)) {
                $line->startY = $row['ly'];
            }
            if (array_key_exists('mx', $row)) {
                $line->endX = $row['mx'];
            }
            if (array_key_exists('my', $row)) {
                $line->endY = $row['my'];
            }
            $lines[] = $line;
        }
        return $lines;
    }

    /**
     * @return int
     */
    private function allocateColor(): int
    {
        if ($this->hasTransparentBackground) {
            imagesavealpha($this->temporaryImage, true);
            $background = imagecolorallocatealpha($this->temporaryImage, 0, 0, 0, 127);
            return $background;
        }

        $background = imagecolorallocate($this->temporaryImage, $this->backgroundRed, $this->backgroundGreen, $this->backgroundBlue);
        return $background;
    }

    /**
     * @param resource $image
     * @param int $startX
     * @param int $startY
     * @param int $endX
     * @param int $endY
     * @param int $color
     * @param int $thickness
     * @return void
     */
    private function drawThickLine(
        $image,
        int $startX,
        int $startY,
        int $endX,
        int $endY,
        int $color,
        int $thickness
    ): void {
        $angle = (atan2(($startY - $endY), ($endX - $startX)));

        $distX = $thickness * (sin($angle));
        $distY = $thickness * (cos($angle));

        $p1x = ceil($startX + $distX);
        $p1y = ceil($startY + $distY);
        $p2x = ceil($endX + $distX);
        $p2y = ceil($endY + $distY);
        $p3x = ceil($endX - $distX);
        $p3y = ceil($endY - $distY);
        $p4x = ceil($startX - $distX);
        $p4y = ceil($startY - $distY);

        $points = [$p1x, $p1y, $p2x, $p2y, $p3x, $p3y, $p4x, $p4y];
        $numberOfPoints = 4;
        imagefilledpolygon($image, $points, $numberOfPoints, $color);
    }

    /**
     * Remove temporary image in case of exception
     */
    public function __destruct()
    {
        if (is_resource($this->temporaryImage)) {
            imagedestroy($this->temporaryImage);
        }
    }
}

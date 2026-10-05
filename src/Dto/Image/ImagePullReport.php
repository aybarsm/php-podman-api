<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Image;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * Outcome of an image pull.
 *
 * Podman sends a sequence of LibpodImagesPullReport documents (progress `stream` lines, then the pulled `images`
 * and `id`). fromDocuments() folds them into one report. A pull can fail after HTTP 200 was sent: check
 * failed() / $error.
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/LibpodImagesPullReport (degraded in v5.8)
 */
final readonly class ImagePullReport implements Hydratable
{
    /**
     * @param list<string> $images IDs of the pulled images
     */
    public function __construct(
        public array $images = [],
        /** Image ID (kept by Podman for backwards compatibility) */
        public ?string $id = null,
        /** Error text from c/image; set when the pull failed after the response started */
        public ?string $error = null,
        /** Progress output from c/image, concatenated */
        public ?string $stream = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            images: Data::stringList($data, 'images'),
            id: Data::stringOrNull($data, 'id'),
            error: Data::errorOrNull($data, 'error'),
            stream: Data::stringOrNull($data, 'stream'),
        );
    }

    /**
     * Folds the documents of a pull response into one report: stream output is concatenated; the last
     * non-empty images, id and error win.
     *
     * @param list<mixed> $documents
     */
    public static function fromDocuments(array $documents): self
    {
        $images = [];
        $id = null;
        $error = null;
        $stream = null;

        foreach ($documents as $document) {
            $report = self::fromArray(Data::map(['document' => $document], 'document'));
            $images = $report->images === [] ? $images : $report->images;
            $id = $report->id ?? $id;
            $error = $report->error ?? $error;
            $stream = $report->stream === null ? $stream : ($stream ?? '').$report->stream;
        }

        return new self($images, $id, $error, $stream);
    }

    public function failed(): bool
    {
        return $this->error !== null;
    }
}

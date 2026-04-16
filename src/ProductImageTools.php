<?php
/**
 * Copyright (c) 2025 PrestaShop SA
 *
 * All Rights Reserved.
 *
 * This module is proprietary software owned by PrestaShop SA. All intellectual property rights, including copyrights, trademarks, and trade secrets, are reserved by PrestaShop SA.
 *
 * The PS MCP Tools module was developed by PrestaShop, which holds all associated intellectual property rights. The license granted to the user does not entail any transfer of rights. The user shall refrain from any act that may infringe upon PrestaShop's rights and undertakes to strictly comply with the limitations of the license set out below. PrestaShop grants the user a personal, non-exclusive, non-transferable, and non-sublicensable license to use the MCP Tools module, worldwide and for the entire duration of use of the module. This license is strictly limited to installing the module and using it solely for the operation of the user's PrestaShop store.
 */

namespace PrestaShop\Module\PsMcpTools;

use PrestaShop\Module\PsMcpTools\Webservice\AbstractWebservice;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Product Image management tools using PrestaShop webservices
 */
class ProductImageTools extends AbstractWebservice
{
    /**
     * Resource name for product images
     */
    private const RESOURCE = 'images/products';

    /**
     * Error message for missing context
     */
    private const ERROR_CONTEXT_NOT_AVAILABLE = 'Context or Language is not available';

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'list_product_image',
        description: 'List all images of a given product. Returns id, position, is_cover, and image_url for each image.'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'productId' => ['type' => 'integer', 'description' => 'ID of the product'],
        ],
        required: ['productId']
    )]
    public function listProductImage(int $productId): array
    {
        // Verify product exists
        $this->verifyProductExists($productId);

        // Get product images - API will automatically use XML format for images
        // and parse the result to a consistent array structure
        $result = $this->getResourceById(self::RESOURCE, $productId, 'full', null);

        $images = [];

        // Parse the response structure to extract image IDs
        $imageIds = $this->extractImageIds($result);

        // Load each image using PrestaShop's Image class to get full details and URLs
        foreach ($imageIds as $imageId) {
            $image = new \Image($imageId);

            if ($image->id) {
                // Get the image URL using PrestaShop's method
                $imageUrl = $this->getImageUrl($image);

                $imageData = [
                    'id' => (int) $image->id,
                    'position' => (int) $image->position,
                    'cover' => (bool) $image->cover,
                    'image_url' => $imageUrl,
                ];

                $images[] = $imageData;
            }
        }

        return $images;
    }

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'add_product_image',
        description: 'Add an image to a product. Accepts base64-encoded image data. Returns the new image ID and URL.'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'productId' => ['type' => 'integer', 'description' => 'ID of the product'],
            'imageData' => ['type' => 'string', 'description' => 'Base64-encoded image data'],
            'position' => ['type' => ['integer', 'null'], 'description' => 'Position of the image (optional)'],
            'cover' => ['type' => ['boolean', 'null'], 'description' => 'Set as cover image (optional, default: false)'],
        ],
        required: ['productId', 'imageData']
    )]
    public function addProductImage(int $productId, string $imageData, ?int $position = null, bool $cover = false): array
    {
        // Verify product exists
        $this->verifyProductExists($productId);

        // Upload the new image and get its ID
        $imageId = $this->uploadNewImage($productId, $imageData);

        // Update position and/or cover if specified
        $this->updateImageMetadataIfNeeded($productId, $imageId, $position, $cover ? true : null);

        // Return the image details
        return $this->buildImageResponse($imageId);
    }

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'update_product_image',
        description: 'Update a product image. Can update position, cover flag, and optionally replace the image file.'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'productId' => ['type' => 'integer', 'description' => 'ID of the product'],
            'imageId' => ['type' => 'integer', 'description' => 'ID of the image to update'],
            'position' => ['type' => ['integer', 'null'], 'description' => 'New position (optional)'],
            'cover' => ['type' => ['boolean', 'null'], 'description' => 'Set as cover image (optional)'],
            'imageData' => ['type' => ['string', 'null'], 'description' => 'Base64-encoded image data, URL, or file path to replace the image (optional)'],
        ],
        required: ['productId', 'imageId']
    )]
    public function updateProductImage(
        int $productId,
        int $imageId,
        ?int $position = null,
        ?bool $cover = null,
        ?string $imageData = null,
    ): array {
        // Verify product exists
        $this->verifyProductExists($productId);

        // Verify image exists and belongs to product
        $this->verifyImageBelongsToProduct($imageId, $productId);

        // Replace image file if provided (returns new image ID)
        if ($imageData !== null) {
            $imageId = $this->replaceImageFile($productId, $imageId, $imageData);
        }

        // Update position and/or cover if specified
        $this->updateImageMetadataIfNeeded($productId, $imageId, $position, $cover);

        // Return the image details
        return $this->buildImageResponse($imageId);
    }

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'set_cover_product_image',
        description: 'Set a specific image as the product cover. Automatically unsets the cover flag on other images.'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'productId' => ['type' => 'integer', 'description' => 'ID of the product'],
            'imageId' => ['type' => 'integer', 'description' => 'ID of the image to set as cover'],
        ],
        required: ['productId', 'imageId']
    )]
    public function setCoverProductImage(int $productId, int $imageId): array
    {
        // Verify product exists
        $this->verifyProductExists($productId);

        // Verify image exists and belongs to product
        $this->verifyImageBelongsToProduct($imageId, $productId);

        // Update image metadata to set as cover
        $this->updateImageMetadata($productId, $imageId, ['cover' => 1]);

        // Return the image details
        return $this->buildImageResponse($imageId);
    }

    /**
     * Extract image IDs from the API response
     *
     * @param array $result API response
     *
     * @return array Array of image IDs
     */
    private function extractImageIds(array $result): array
    {
        if (!isset($result['image'])) {
            return [];
        }

        $imageData = $result['image'];

        // Check if declination array exists (most common case)
        if (isset($imageData['declination']) && is_array($imageData['declination'])) {
            return $this->extractIdsFromDeclination($imageData['declination']);
        }

        // Single image with ID at root level
        if (isset($imageData['id'])) {
            return $this->extractIdsFromImageData($imageData);
        }

        // Multiple images - check if it's an array of image objects
        if (is_array($imageData)) {
            return $this->extractIdsFromMultipleImages($imageData);
        }

        return [];
    }

    /**
     * Extract IDs from declination array
     *
     * @param array $declinations Declination array
     *
     * @return array Array of image IDs
     */
    private function extractIdsFromDeclination(array $declinations): array
    {
        $imageIds = [];
        foreach ($declinations as $decl) {
            if (isset($decl['id'])) {
                $imageIds[] = (int) $decl['id'];
            }
        }

        return $imageIds;
    }

    /**
     * Extract IDs from multiple image objects
     *
     * @param array $images Array of image objects
     *
     * @return array Array of image IDs
     */
    private function extractIdsFromMultipleImages(array $images): array
    {
        $imageIds = [];
        foreach ($images as $img) {
            if (is_array($img) && isset($img['id'])) {
                $ids = $this->extractIdsFromImageData($img);
                $imageIds = array_merge($imageIds, $ids);
            }
        }

        return $imageIds;
    }

    /**
     * Extract IDs from a single image data structure
     *
     * @param array $imageData Image data from API response
     *
     * @return array Array of image IDs
     */
    private function extractIdsFromImageData(array $imageData): array
    {
        $ids = [];

        // Check if there are declinations (multiple image IDs)
        if (isset($imageData['declination']) && is_array($imageData['declination'])) {
            foreach ($imageData['declination'] as $declination) {
                if (isset($declination['id'])) {
                    $ids[] = (int) $declination['id'];
                }
            }
        } elseif (isset($imageData['id'])) {
            // No declinations, just the main image ID
            $ids[] = (int) $imageData['id'];
        }

        return $ids;
    }

    /**
     * Get the front-end image URL for a product image
     *
     * @param \Image $image Image object
     *
     * @return string Image URL
     */
    private function getImageUrl(\Image $image): string
    {
        $context = \Context::getContext();
        if ($context === null || $context->link === null) {
            throw new \PrestaShopException('Context or Link is not available');
        }

        $link = $context->link;

        // Use PrestaShop's Link class to generate the proper image URL
        // This automatically handles the image path format (e.g., /img/p/2/2/22.jpg)
        return $link->getImageLink(
            '', // link_rewrite (not needed for direct image URL)
            (string) $image->id,
            \ImageType::getFormattedName('large') // Use 'large' image type, or adjust as needed
        );
    }

    /**
     * Prepare image file from base64 or URL
     *
     * @param string $imageData Base64-encoded data or URL
     *
     * @return string Path to temporary file
     *
     * @throws \PrestaShopException If image preparation fails
     */
    private function prepareImageFile(string $imageData): string
    {
        // Create secure temporary directory if it doesn't exist
        $tmpDir = _PS_ROOT_DIR_ . '/var/tmp';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $tmpFile = tempnam($tmpDir, 'img_');

        // Check if it's a URL
        if (filter_var($imageData, FILTER_VALIDATE_URL)) {
            // Download from URL
            $content = file_get_contents($imageData);
            if ($content === false) {
                throw new \PrestaShopException('Failed to download image from URL');
            }
            file_put_contents($tmpFile, $content);
        } else {
            // Assume base64-encoded data
            // Remove data URI scheme if present (e.g., "data:image/jpeg;base64,")
            if (preg_match('/^data:image\/\w+;base64,/', $imageData)) {
                $cleaned = preg_replace('/^data:image\/\w+;base64,/', '', $imageData);
                if ($cleaned === null) {
                    throw new \PrestaShopException('Failed to clean image data URI');
                }
                $imageData = $cleaned;
            }

            $decoded = base64_decode($imageData, true);
            if ($decoded === false) {
                throw new \PrestaShopException('Failed to decode base64 image data');
            }
            file_put_contents($tmpFile, $decoded);
        }

        // Verify it's a valid image
        $imageInfo = @getimagesize($tmpFile);
        if ($imageInfo === false) {
            unlink($tmpFile);
            throw new \PrestaShopException('Invalid image file');
        }

        return $tmpFile;
    }

    /**
     * Update image metadata (position, cover) via webservice and Image object
     * For cover: updates both product's id_default_image (webservice) AND image cover flags (Image object)
     * For position: updates the image position via Image object (no WS support)
     *
     * @param int $productId Product ID
     * @param int $imageId Image ID
     * @param array $data Data to update (position, cover)
     *
     * @throws \PrestaShopException If update fails
     */
    private function updateImageMetadata(int $productId, int $imageId, array $data): void
    {
        // Verify image exists and belongs to product
        $image = new \Image($imageId);
        if (!$image->id) {
            throw new \PrestaShopException("Image with ID {$imageId} not found");
        }
        if ((int) $image->id_product !== $productId) {
            throw new \PrestaShopException("Image {$imageId} does not belong to product {$productId}");
        }

        // IMPORTANT: Handle position BEFORE cover
        // Setting cover via webservice may affect position, so we set position first
        if (isset($data['position']) && (int) $image->position !== (int) $data['position']) {
            $this->updateImagePosition($image, (int) $data['position']);
        }

        // Handle cover update AFTER position
        if (isset($data['cover']) && (bool) $data['cover']) {
            $this->setImageAsCover($productId, $imageId);
        }
    }

    /**
     * Set an image as the product cover
     * Updates both product's id_default_image and image cover flags
     *
     * @param int $productId Product ID
     * @param int $imageId Image ID to set as cover
     *
     * @throws \PrestaShopException If update fails
     */
    private function setImageAsCover(int $productId, int $imageId): void
    {
        // Load the image
        $image = new \Image($imageId);
        if (!$image->id) {
            throw new \PrestaShopException("Image with ID {$imageId} not found");
        }

        // Step 1: Update product's id_default_image via webservice
        $this->updateResource('products', $productId, [
            'id_default_image' => $imageId,
        ]);

        // Step 2: Remove cover from all other images
        $this->removeCoverFromOtherImages($productId, $imageId);

        // Step 3: Set this image as cover
        $image->cover = true;
        if (!$image->update()) {
            throw new \PrestaShopException('Failed to set image as cover');
        }
    }

    /**
     * Remove cover flag from all images except the specified one
     *
     * @param int $productId Product ID
     * @param int $excludeImageId Image ID to exclude from update
     */
    private function removeCoverFromOtherImages(int $productId, int $excludeImageId): void
    {
        $context = \Context::getContext();
        if ($context === null || $context->language === null) {
            throw new \PrestaShopException(self::ERROR_CONTEXT_NOT_AVAILABLE);
        }

        $allImages = \Image::getImages((int) $context->language->id, $productId);
        foreach ($allImages as $img) {
            if ((int) $img['id_image'] === $excludeImageId) {
                continue;
            }

            $imgObj = new \Image($img['id_image']);
            if ($imgObj->id) {
                $imgObj->cover = false;
                $imgObj->update();
            }
        }
    }

    /**
     * Update image position
     * Uses Image class directly as webservice doesn't support PUT on images/products
     * Also reorders all other images to maintain sequential positions
     *
     * @param \Image $image Image object
     * @param int $position New position
     *
     * @throws \PrestaShopException If update fails
     */
    private function updateImagePosition(\Image $image, int $position): void
    {
        $productId = (int) $image->id_product;
        $imageId = (int) $image->id;

        // Get all images for this product
        $context = \Context::getContext();
        if ($context === null || $context->language === null) {
            throw new \PrestaShopException(self::ERROR_CONTEXT_NOT_AVAILABLE);
        }

        $allImages = \Image::getImages($context->language->id, $productId);

        // Sort by current position, then by ID to have a consistent order
        usort($allImages, function ($a, $b) {
            if ($a['position'] === $b['position']) {
                return $a['id_image'] - $b['id_image'];
            }

            return $a['position'] - $b['position'];
        });

        // Build new position mapping
        $newPositions = [];
        $currentPos = 1;

        // First pass: assign positions to all images except the target one
        foreach ($allImages as $img) {
            if ((int) $img['id_image'] === $imageId) {
                continue; // Skip our target image
            }

            // If we've reached the desired position, skip it for our image
            if ($currentPos === $position) {
                ++$currentPos;
            }

            $newPositions[(int) $img['id_image']] = $currentPos;
            ++$currentPos;
        }

        // Add our target image at the desired position
        $newPositions[$imageId] = $position;

        // Update all positions in the database
        foreach ($newPositions as $imgId => $newPos) {
            $img = new \Image($imgId);
            if ($img->id) {
                $img->position = $newPos;
                $img->update();
            }
        }

        // Update the local image object
        $image->position = $position;
    }

    /**
     * Extract image ID from various PrestaShop API response structures
     * Different PrestaShop versions may return different response formats
     *
     * @param array $parsed Parsed API response
     *
     * @return int|null Image ID or null if not found
     */
    private function extractImageIdFromResponse(array $parsed): ?int
    {
        // Define all possible paths to image ID in different response structures
        // Note: @attributes removed since new XML parser doesn't use them
        $possiblePaths = [
            ['image', 'id'],                    // Structure 1: Direct image node
            ['images', 'image', 'id'],          // Structure 2: Images array
            ['id'],                             // Structure 3: Top-level id
            ['prestashop', 'image', 'id'],      // Structure 4: Prestashop wrapper
            ['prestashop', 'images', 'image', 'id'], // Structure 5: Prestashop wrapper with images
        ];

        // Try each path until we find a valid ID
        foreach ($possiblePaths as $path) {
            $value = $this->getValueByPath($parsed, $path);
            if ($value !== null && $value !== '') {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * Get a value from a nested array by following a path
     *
     * @param array $array Array to search
     * @param array $path Path to follow (array of keys)
     *
     * @return mixed|null Value found or null
     */
    private function getValueByPath(array $array, array $path)
    {
        $current = $array;

        foreach ($path as $key) {
            if (!is_array($current) || !isset($current[$key])) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }

    /**
     * Get the ID of the most recently added image for a product
     * This is useful when PrestaShop returns empty response after upload
     *
     * @param int $productId Product ID
     *
     * @return int|null Latest image ID or null if no images found
     */
    private function getLatestImageIdForProduct(int $productId): ?int
    {
        try {
            // Get all images for the product
            $result = $this->getResourceById(self::RESOURCE, $productId, 'full', null);
            $imageIds = $this->extractImageIds($result);

            // Return the highest ID (most recently created)
            if (!empty($imageIds)) {
                return max($imageIds);
            }
        } catch (\Exception) {
            // If we can't get images, return null
            return null;
        }

        return null;
    }

    /**
     * Verify that a product exists
     *
     * @param int $productId Product ID
     *
     * @throws \PrestaShopException If product doesn't exist
     */
    private function verifyProductExists(int $productId): void
    {
        $product = new \Product($productId);
        if (!$product->id) {
            throw new \PrestaShopException("Product with ID {$productId} does not exist");
        }
    }

    /**
     * Verify that an image exists and belongs to a product
     *
     * @param int $imageId Image ID
     * @param int $productId Product ID
     *
     * @throws \PrestaShopException If image doesn't exist or doesn't belong to product
     */
    private function verifyImageBelongsToProduct(int $imageId, int $productId): void
    {
        $image = new \Image($imageId);
        if (!$image->id) {
            throw new \PrestaShopException("Image with ID {$imageId} does not exist");
        }
        if ((int) $image->id_product !== $productId) {
            throw new \PrestaShopException("Image {$imageId} does not belong to product {$productId}");
        }
    }

    /**
     * Replace an image file with a new one
     * Deletes the old image and uploads a new one, returning the new image ID
     *
     * @param int $productId Product ID
     * @param int $oldImageId Old image ID to replace
     * @param string $imageData New image data (base64 or URL)
     *
     * @return int New image ID
     *
     * @throws \PrestaShopException If replacement fails
     */
    private function replaceImageFile(int $productId, int $oldImageId, string $imageData): int
    {
        // Delete old image via webservice
        $this->deleteResource("images/products/{$productId}/{$oldImageId}");

        // Upload new image
        $tmpFile = $this->prepareImageFile($imageData);
        try {
            $result = $this->uploadBinaryFile("images/products/{$productId}", $tmpFile);
            $parsed = $this->parseWebserviceResponse($result);

            // Extract new image ID
            $newImageId = $this->extractImageIdFromResponse($parsed);

            // If response is empty but HTTP code is success, get the last added image
            if (!$newImageId && isset($result['code']) && ($result['code'] == 200 || $result['code'] == 201)) {
                $newImageId = $this->getLatestImageIdForProduct($productId);
            }

            if (!$newImageId) {
                throw new \PrestaShopException('Failed to get new image ID after replacing image');
            }

            return $newImageId;
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    /**
     * Update image metadata (position, cover) if values are provided
     *
     * @param int $productId Product ID
     * @param int $imageId Image ID
     * @param int|null $position New position (optional)
     * @param bool|null $cover New cover flag (optional)
     */
    private function updateImageMetadataIfNeeded(int $productId, int $imageId, ?int $position, ?bool $cover): void
    {
        if ($position === null && $cover === null) {
            return;
        }

        $updateData = [];
        if ($position !== null) {
            $updateData['position'] = $position;
        }
        if ($cover !== null) {
            $updateData['cover'] = $cover ? 1 : 0;
        }

        $this->updateImageMetadata($productId, $imageId, $updateData);
    }

    /**
     * Upload a new image to a product
     * Handles file preparation, upload, and ID extraction
     *
     * @param int $productId Product ID
     * @param string $imageData Image data (base64 or URL)
     *
     * @return int New image ID
     *
     * @throws \PrestaShopException If upload fails
     */
    private function uploadNewImage(int $productId, string $imageData): int
    {
        // Prepare the image file
        $tmpFile = $this->prepareImageFile($imageData);

        try {
            // Upload image via webservice
            $result = $this->uploadBinaryFile("images/products/{$productId}", $tmpFile);
            $parsed = $this->parseWebserviceResponse($result);

            $imageId = $parsed['image']['id'] ?? null;

            if (!$imageId) {
                $responsePreview = json_encode($parsed, JSON_PRETTY_PRINT);
                if ($responsePreview === false) {
                    $responsePreview = 'Unable to encode response';
                } elseif (strlen($responsePreview) > 500) {
                    $responsePreview = substr($responsePreview, 0, 500) . '...';
                }

                $httpCode = $result['code'] ?? 'unknown';
                $errorMsg = "Failed to extract image ID from response.\n";
                $errorMsg .= "HTTP Code: {$httpCode}\n";
                $errorMsg .= "Response structure: {$responsePreview}\n";
                $errorMsg .= 'Tried fallback getLatestImageIdForProduct but got no result.';

                throw new \PrestaShopException($errorMsg);
            }

            return $imageId;
        } finally {
            // Clean up temporary file
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    /**
     * Build image response with all details
     * Reloads image from database to ensure fresh data
     *
     * @param int $imageId Image ID
     *
     * @return array Image details (id, position, cover, image_url)
     */
    private function buildImageResponse(int $imageId): array
    {
        // Get the image details (reload to ensure we have updated values)
        $image = new \Image($imageId);
        $image->clearCache();

        return [
            'id' => $imageId,
            'position' => (int) $image->position,
            'cover' => (bool) $image->cover,
            'image_url' => $this->getImageUrl($image),
        ];
    }
}

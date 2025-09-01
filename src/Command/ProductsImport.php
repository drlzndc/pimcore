<?php

namespace App\Command;

use Exception;
use RuntimeException;
use Elements\Bundle\ProcessManagerBundle\ExecutionTrait;
use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Pimcore\Model\Asset\Image;
use Pimcore\Model\DataObject\Brand;
use Pimcore\Model\DataObject\Category;
use Pimcore\Model\DataObject\Classificationstore;
use Pimcore\Model\DataObject\Classificationstore\GroupConfig;
use Pimcore\Model\DataObject\Classificationstore\KeyConfig;
use Pimcore\Model\DataObject\Classificationstore\StoreConfig;
use Pimcore\Model\DataObject\Data\QuantityValue;
use Pimcore\Model\DataObject\Folder;
use Symfony\Component\HttpKernel\KernelInterface;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\QuantityValue\Unit;
use Pimcore\Model\Element\Service;
use Psr\Log\LoggerInterface;


class ProductsImport extends AbstractCommand
{
    use ExecutionTrait;


    /**
     * ID value which can be seen in admin panel
     *
     * @var int
     */
    private int $folderId = 51;

    /**
     * Folder in which the Product objects are stored
     *
     * @var Folder
     */
    private Folder $folder;

    /**
     * Path to the .xlsx file
     *
     * @var string
     */
    private string $filePath;

    /**
     * Mapping of document columns to their validators
     *
     * @var array
     */
    private array $validators = [
        'name'        => 'validateName',
        'description' => 'validateDescription',
        'image'       => 'validateImage',
        'categories'  => 'validateCategories',
        'brand'       => 'validateBrand',
        'sku'         => 'validateSku',
        'price'       => 'validatePrice',
        'stock'       => 'validateStock',
        'status'      => 'validateStatus',
        'attributes'  => 'validateAttributes'
    ];

    protected function configure(): void
    {
        $this
            ->setName('products:import')
            ->setDescription('Imports products from local .xlsx file');
    }

    private LoggerInterface $logger;


    /**
     * Constructs the command and sets values for the file path and folder
     *
     * @param KernelInterface $kernel
     */
    public function __construct(KernelInterface $kernel, LoggerInterface $logger)
    {
        $this->filePath = $kernel->getProjectDir()
            . '/var/import/products_import.xlsx';

        $this->folder = Folder::getById($this->folderId);

        $this->logger = $logger;

        parent::__construct();
    }

    /**
     * Executes the command and imports the products
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return integer
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $validators   = array_values($this->validators);
        $columns      = array_keys($this->validators);
        $valuesPerRow = count($columns);

        try {
            $sheet = (new Xlsx())
                ->load($this->filePath)
                ->getActiveSheet();

            $rowIndex = -1;
            foreach ($sheet->getRowIterator() as $row) {
                $rowIndex++;
                $totalValues = 0;
                $productData = [];

                $cellIndex = -1;
                foreach ($row->getCellIterator() as $cell) {
                    $cellIndex++;
                    if ($cellIndex >= $valuesPerRow) {
                        throw new Exception("Too many values in row $rowIndex");
                    }

                    $column = $columns[$cellIndex];
                    $value  = $cell->getValue();
                    $totalValues++;

                    // Header row
                    if ($rowIndex === 0) {
                        if ($value !== $column) {
                            throw new Exception(
                                "Cell $cellIndex in header row should be '$column'"
                            );
                        }
                        continue 2;
                    }

                    try {
                        $productData[$column] = $this->{$validators[$cellIndex]}($value);
                    } catch (Exception $e) {
                        throw new Exception(
                            "Invalid value in row $rowIndex, cell $cellIndex: $e"
                        );
                    }
                }

                if ($totalValues < $valuesPerRow) {
                    throw new Exception("Not enough values in row $rowIndex");
                }

                $this->importProduct($productData);
            }

            return self::SUCCESS;

        } catch (Exception $e) {
            $eClass = get_class($e);
            $this->logger->error("Caught $eClass: '$e'");

            return self::FAILURE;
        }
    }


    /**
     * Imports product with given data
     *
     * @param array $productData
     * @return void
     * 
     * @throws RuntimeException
     */
    private function importProduct(array $productData): void
    {
        $product = Product::getBySku($productData['sku'], 1);
        if (!$product) {
            $product = new Product();
            $product->setParent($this->folder);
            $product->setKey(Service::getValidKey($productData['sku'], 'object'));
        }

        $product->setPublished(true);
        $product->setName($productData['name']);
        $product->setDescription($productData['description']);
        $product->setImage($productData['image']);
        $product->setCategories($productData['categories']);
        $product->setBrand($productData['brand']);
        $product->setSku($productData['sku']);
        $product->setPrice($productData['price']);
        $product->setStock($productData['stock']);
        $product->setStatus($productData['status']);
        $product->setTechnicalAttributes($productData['attributes']);

        try {
            $product->save();

        } catch (Exception $e) {
            throw new RuntimeException(
                "Could not save product ($productData[sku]), caught exception '$e'"
            );
        }
    }

    /**
     * Validates "name" column entry and returns value ready for import
     *
     * @param string|null $name
     * @return string
     * 
     * @throws Exception
     */
    private function validateName(?string $name): string
    {
        if (!$name) {
            throw new Exception("Product name is required");
        }

        return $name;
    }

    /**
     * Validates "description" column entry and returns value ready for import
     *
     * @param string|null $description
     * @return string
     * 
     * @throws Exception
     */
    private function validateDescription(?string $description): string
    {
        if (!$description) {
            throw new Exception("Product description is required");
        }

        return $description;
    }

    /**
     * Validates "image" column entry and returns value ready for import
     *
     * @param string|null $imagePath
     * @return Image|null
     */
    private function validateImage(?string $imagePath): ?Image
    {
        if (!$imagePath) {
            return null;
        }

        return Image::getByPath($imagePath);
    }

    /**
     * Validates "categories" column entry and returns value ready for import
     *
     * @param string|null $categoriesList
     * @return array
     * 
     * @throws Exception
     */
    private function validateCategories(?string $categoriesList): array
    {
        if (!$categoriesList) {
            throw new Exception("One or more categories is required");
        }

        $categories = [];
        foreach (explode(",", $categoriesList) as $name) {
            $category = Category::getByName($name, 1);
            if (!$category) {
                throw new Exception("Invalid category '$name'");
            }
            $categories[] = $category;
        }

        return $categories;
    }

    /**
     * Validates "brand" column entry and returns value ready for import
     *
     * @param string|null $brandName
     * @return Brand
     * 
     * @throws Exception
     */
    private function validateBrand(?string $brandName): Brand
    {
        if (!$brandName) {
            throw new Exception("Brand is required");
        }

        $brand = Brand::getByName($brandName, 1);
        if (!$brand) {
            throw new Exception("Invalid brand $brandName");
        }

        return $brand;
    }

    /**
     * Validates "sku" column entry and returns value ready for import
     *
     * @param string|null $sku
     * @return string
     * 
     * @throws Exception
     */
    private function validateSku(?string $sku): string
    {
        if (!$sku) {
            throw new Exception("SKU is required");
        }

        // Validate format using regex
        if (!preg_match('/PROD-\d{3,4}/', $sku, $matches)) {
            throw new Exception("Invalid SKU format");
        }

        return $sku;
    }

    /**
     * Validates "price" column entry and returns value ready for import
     *
     * @param string|null $price
     * @return float|null
     * 
     * @throws Exception
     */
    private function validatePrice(?string $price): ?float
    {
        if (!$price) {
            return null;
        }

        if (!is_numeric($price) || $price < 0) {
            throw new Exception("Price should be a non-negative number");
        }

        return (float) $price;
    }

    /**
     * Validates "stock" column entry and returns value ready for import
     *
     * @param string|null $stock
     * @return int|null
     * 
     * @throws Exception
     */
    private function validateStock(?string $stock): ?int
    {
        if (!$stock) {
            return null;
        }

        if (!is_numeric($stock) || $stock < 0) {
            throw new Exception("Stock should be a non-negative integer");
        }

        return (int) $stock;
    }

    /**
     * Validates "status" column entry and returns value ready for import
     *
     * @param string|null $status
     * @return string
     * 
     * @throws Exception
     */
    private function validateStatus(?string $status): string
    {
        $validStatuses = ['active', 'inactive'];

        if (!$status) {
            throw new Exception("Status is required");
        }

        if (!in_array($status, $validStatuses)) {
            throw new Exception("Invalid status $status");
        }

        return $status;
    }

    /**
     * Validates "attributes" column entry and returns value ready for import
     *
     * @param string|null $attributes
     * @return Classificationstore|null
     * 
     * @throws Exception
     */
    private function validateAttributes(?string $attributes): ?Classificationstore
    {
        if (!$attributes) {
            return null;
        }

        $data = json_decode(stripcslashes($attributes), true);
        if (!$data) {
            throw new Exception("Invalid format for 'attributes'");
        }

        $storeId = StoreConfig::getByName("ProductAttributes")->getId();

        $store = new Classificationstore();
        $store->setFieldname("technicalAttributes");
        $store->setObject(new Product());

        foreach ($data as $groupName => $groupAttributes) {
            $group = GroupConfig::getByName($groupName, $storeId);
            if (!$group) {
                throw new Exception("Invalid attributes group '$groupName'");
            }

            foreach ($groupAttributes as $keyName => $data) {
                $key = KeyConfig::getByName($keyName, $storeId);
                if (!$key) {
                    throw new Exception("Invalid attributes key '$keyName'");
                }

                $unit = Unit::getByAbbreviation($data['unit']);
                if (!$unit) {
                    throw new Exception("Invalid unit '$data[unit]'");
                }

                $store = $store->setLocalizedKeyValue(
                    $group->getId(),
                    $key->getId(),
                    new QuantityValue($data['value'], $unit->getId())
                );
            }
        }

        return $store;
    }
}

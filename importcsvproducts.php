<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class ImportCsvProducts extends Module
{
    public function __construct()
    {
        $this->name = 'importcsvproducts';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Carlos Andrés Arévalo Cortés';
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('Importar Productos desde CSV');
        $this->description = $this->l('Importa productos desde un archivo CSV.');

    }

    public function install()
    {
        return parent::install();
    }

  
    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('import_csv')) {
            $csvFile = $_FILES['csv_file']['tmp_name'];

            if (is_uploaded_file($csvFile) && $this->isCsvFile($csvFile)) {
                $rows = array_map('str_getcsv', file($csvFile));
                $header = array_shift($rows);
                $error = false;
                $confirmation = false;

                $successCount = 0;

                foreach ($rows as $row) {
                    $productData = array_combine($header, $row);
                    $product = new Product();
                    $product->name = array(Configuration::get('PS_LANG_DEFAULT') => $productData['Nombre']);
                    $product->reference = $productData['Referencia'];
                    $product->ean13 = $productData['EAN13'];
                    $product->price = $productData['Precio de venta'];
                    $product->wholesale_price = $productData['Precio de coste'];

                    if ($product->add()) {
                        $successCount++;
                        $output .= $this->displayConfirmation($this->l('Producto importado: ') . $product->name[Configuration::get('PS_LANG_DEFAULT')] . '<br>');
                        StockAvailable::setQuantity($product->id, null, (int)$productData['Cantidad']);
                    
                        $categories = explode(';', $productData['Categorias']); // Obtener las categorías del CSV
                        $parentCategoryId = 2; // ID de la categoría padre 'Inicio'

                        $product->addToCategories(array($parentCategoryId));
                    
                        foreach ($categories as $categoryName) {
                            $categoryId = $this->getCategoryIdByNameAndParent($categoryName, $parentCategoryId);
                    
                            if ($categoryId === false) {
                                // La categoría no existe, así que la creamos y luego asociamos al producto
                                $newCategory = new Category();
                                $newCategory->name = array(Configuration::get('PS_LANG_DEFAULT') => $categoryName);
                                $newCategory->id_parent = $parentCategoryId;
                    
                                // Establecer el atributo link_rewrite
                                $link_rewrite = Tools::link_rewrite($categoryName);
                                $newCategory->link_rewrite[(int) Configuration::get('PS_LANG_DEFAULT')] = $link_rewrite;
                    
                                $newCategory->add();
                    
                                // Asociamos la categoría recién creada al producto
                                $product->addToCategories(array($newCategory->id));
                            } else {
                                // La categoría ya existe, simplemente la asociamos al producto
                                $product->addToCategories(array($categoryId));
                            }
                        }
                    } else {
                        $output .= $this->displayError($this->l('Error al importar el producto: ') . $product->name[Configuration::get('PS_LANG_DEFAULT')] . '<br>');
                        $error = true;
                    }
                }

                if ($successCount > 0) {
                    $output .= $this->displayConfirmation($this->l('Productos importados exitosamente: '));
                    $confirmation = true;
                }
            } else {
                $output .= $this->displayError($this->l('Error al cargar el archivo CSV.'));
                $error = true;
            }
        }

        $this->context->smarty->assign([
            'output' => $output,
            'error' => $error,
            'confirmation' => $confirmation
        ]);
        return $this->display(__FILE__, 'views/templates/admin/import.tpl');
    }

    /**
     * @param mixed $file
     * 
     * @return [type]
     */
    private function isCsvFile($file)
    {
        $mimeTypes = array('text/csv', 'text/plain', 'application/csv', 'text/comma-separated-values', 'application/excel', 'application/vnd.ms-excel', 'application/vnd.msexcel');
        $fileMimeType = mime_content_type($file);
        return in_array($fileMimeType, $mimeTypes);
    }

    // Función para obtener el ID de la categoría por nombre y categoría padre
    /**
     * @param mixed $categoryName
     * @param mixed $parentCategoryId
     * 
     * @return [type]
     */
    private function getCategoryIdByNameAndParent($categoryName, $parentCategoryId) {
        $idLang = Context::getContext()->language->id;
        $categories = Category::getCategories($idLang, false, false);

        foreach ($categories as $category) {
            if ($category['name'] == $categoryName && $category['id_parent'] == $parentCategoryId) {
                return $category['id_category'];
            }
        }

        return false;
    }
}

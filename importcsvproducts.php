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
                    $product->reference = $productData['Referencia'];
                    $product->ean13 = $productData['EAN13'];
                    $product->price = $productData['Precio de venta'];
                    $product->wholesale_price = $productData['Precio de coste'];
                    $ivaPercentage = (float)$productData['IVA'];
                    $taxRuleGroupId = $this->getTaxRuleGroupIdByPercentage($ivaPercentage); 
                    $product->id_tax_rules_group = (int)$taxRuleGroupId;
                    $product->id_category_default = 2;
                    
                    foreach (Language::getLanguages(true) as $language) {
                        $product->name[$language['id_lang']] = $productData['Nombre'];
                    }




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

    
    /**
     * @param mixed $percentage
     * 
     * @return int
     */
    private function getTaxRuleGroupIdByPercentage($percentage): int
    {
        // Obtener el ID del país actual (puedes usar la lógica adecuada para obtenerlo)
        $currentCountryId = (int)Context::getContext()->country->id;

        $sql = 'SELECT trg.id_tax_rules_group FROM ' . _DB_PREFIX_ . 'tax_rules_group trg
                INNER JOIN ' . _DB_PREFIX_ . 'tax_rule tr ON tr.id_tax_rules_group = trg.id_tax_rules_group
                INNER JOIN ' . _DB_PREFIX_ . 'tax t ON t.id_tax = tr.id_tax
                WHERE t.rate = ' . (float)$percentage . ' AND tr.id_country = ' . $currentCountryId;
                
        $existingGroupId = Db::getInstance()->getValue($sql);

        if ($existingGroupId) {
            return $existingGroupId; // Devolver el ID del grupo existente
        }

        // Si no existe, crear un nuevo grupo de reglas de impuestos
        $newTaxRuleGroup = new TaxRulesGroup();
        $newTaxRuleGroup->name = 'IVA ' . $percentage . '%';
        $newTaxRuleGroup->active = 1;
        $newTaxRuleGroup->deleted = 0;
        $newTaxRuleGroup->add();

        // Crear la regla de impuestos dentro del nuevo grupo
        $newTaxRule = new TaxRule();
        $newTaxRule->id_tax_rules_group = $newTaxRuleGroup->id;
        $newTaxRule->id_country = $currentCountryId; // Asociar al país actual
        $newTaxRule->id_state = 0;                   // Puedes establecer un estado específico si es necesario
        $newTaxRule->id_tax = $this->getOrCreateTaxByPercentage($percentage);
        $newTaxRule->add();

        return $newTaxRuleGroup->id; // Devolver el ID del nuevo grupo creado
    }




    /**
     * @param mixed $percentage
     * 
     * @return int
     */
    private function getOrCreateTaxByPercentage($percentage): ?int
    {
        $taxes = Tax::getTaxes($this->context->language->id);
    
        foreach ($taxes as $tax) {
            if ($tax['rate'] == (float)$percentage) {
                return $tax['id_tax'];
            }
        }
    
        $newTax = new Tax();
        $newTax->rate = (float)$percentage;
        $newTax->name = array((int)$this->context->language->id => 'IVA ' . $percentage . '%'); // Establecer el nombre del impuesto
        $newTax->active = 1;
        $newTax->deleted = 0;
    
        if ($newTax->add()) {
            return $newTax->id;
        }
    
        return false; // En caso de error
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
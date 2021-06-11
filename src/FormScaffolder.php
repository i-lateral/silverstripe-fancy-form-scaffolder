<?php

namespace ilateral\SilverStripe\FancyFormScaffolder;

use LogicException;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\Forms\FormScaffolder as CMSFormScaffolder;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;

class FormScaffolder extends CMSFormScaffolder
{
    const HEADING_LEVELS = [
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6'
    ];

    /**
     * The current tab we are working from for generation
     *
     * @var string
     */
    protected $curr_tab;

    /**
     * Either generate a field list (using the provided config variable) or fall back to
     * using the default field generator.
     *
     * @return FieldList
     */
    public function getFieldList()
    {
        $obj = $this->obj;
        $field_config = $obj->config()->cms_fields;

        if (empty($field_config) || (!empty($field_config) && !is_array($field_config))) {
            return parent::getFieldList();
        }

        $fields = FieldList::create();

        // tabbed or untabbed
        if ($this->tabbed) {
            $fields->push(TabSet::create("Root"));
        }

        $fields = $this->generateRecursiveSubFields($field_config, $fields);

        return $fields;
    }

    /**
     * Generate a list of fields recursivley from the provided array
     *
     * @param array     $array  Array of field setup
     * @param FieldList $fields Mast field list to add fields to
     *
     * @return FieldList
     */
    protected function generateRecursiveSubFields(array $array, FieldList $fields)
    {
        foreach ($array as $key => $value) {
            // If this is a tab, store it
            if ($this->tabbed && strpos($key, '.')) {
                $this->curr_tab = $key;
            }

            // If we have an instance of a Composite field, then add fields
            if (is_array($value) && isset($value['type'])
                && is_a($value['type'], CompositeField::class, true)
                && isset($value['fields']
            )) {
                $name = (!empty($key)) ? $key : "";

                $this->addField(
                    $this->createCompositeField($value['type'], $value['fields'], $name),
                    $fields
                );
            }

            if ($key != 'fields' && is_array($value)) {
                $fields = $this->generateRecursiveSubFields($value, $fields);
            }

            if ($key == 'fields' && is_array($value)) {
                $fields = $this->processFieldList($value, $fields);
            }
        }

        return $fields;
    }

    /**
     * Process an array of field names OR types and settings and return
     * the udated list
     *
     * @param array A list of fields to check
     *
     * @return FieldList
     */
    protected function processFieldList(array $list, FieldList $fields)
    {
        foreach ($list as $key => $value) {
            $field = null;

            if (is_string($value) && in_array(strtolower($value), self::HEADING_LEVELS)) {
                // Check for heading fields
                $field = $this->createHeadingField($value, $key);
            } else if (is_int($key)) {
                // If a standard field, get from object and apply
                $field = $this->getFieldObject($value);
            } else if (is_array($value) && isset($value['type'])
            && is_a($value['type'], CompositeField::class, true)
            && isset($value['fields']
            )) {
                // If this is a type of composite field, setup the field and children
                $name = (!empty($key)) ? $key : "";
                $field = $this->createCompositeField(
                    $value['type'],
                    $value['fields'],
                    $name
                );
            }

            if (isset($field)) {
                $field = $this->processField($field, $value);
                $fields = $this->addField($field, $fields);
            }
        }

        return $fields;
    }

    /**
     * Process a given field, setting up methods and then return
     * 
     * @param FormField $field
     * @param mixed $value
     *
     * @return FormField
     */
    protected function processField(FormField $field, $value)
    {
        if (!is_array($value)) {
            return $field;
        }

        if (isset($value['methods'])) {
            foreach ($value['methods'] as $method => $args) {
                if (!$field->hasMethod($method)) {
                    continue;
                }

                if (!is_array($args)) {
                    $args = [$args];
                }

                // unpack args and pass them to the called method
                $field->$method(...$args);
            }
        }

        return $field;
    }

    /**
     * Create a composite field containing passed fields with the optional name
     *
     * @param string $field_class The classname of the field to create
     * @param array  $fields      A list of fields to add
     * @param string $name        Optional name of the field
     *
     * @return CompositeField
     */
    protected function createCompositeField(string $field_class, array $fields, string $name = "")
    {
        if (!is_a($field_class, CompositeField::class, true)) {
            throw new LogicException('Field: ' . $field_class . ' is not a type of composite field');
        }

        $tabbed = $this->tabbed;
        $this->tabbed = false;

        // Toggle composite fields take different params on construction
        if (is_a($field_class, ToggleCompositeField::class, true)) {
            /** @var ToggleCompositeField */
            $field = $field_class::create(
                'Toggle' . Convert::raw2url($name),
                $name,
                $this->generateRecursiveSubFields(['fields' => $fields], FieldList::create())
            );
        } else {
            /** @var CompositeField */
            $field = $field_class::create(
                $this->generateRecursiveSubFields(['fields' => $fields], FieldList::create())
            );
        }

        if (!empty($name)) {
            $field->setName(Convert::raw2url($name));
            $field->setTitle($name);
        }

        $this->tabbed = $tabbed;

        return $field;
    }

    /**
     * Creating a heading field based on the level/title
     *
     * @param string|int $level The heading level
     * @param string     $title The text for the heading
     *
     * @return HeaderField
     */
    protected function createHeadingField($level, $title)
    {
        // Ensure we strip the "h" off the begining
        if (strlen($level) > 1) {
            $level = substr($level, 1, 1);
        }

        return HeaderField::create(
            'Heading' . Convert::raw2url($title),
            $this->obj->fieldLabel($title),
            $level
        );
    }

    /**
     * Return a relevent field object (if it can be found). If not,
     * will return a ReadonlyField and attempt to set it's value.
     *
     * @return \SilverStripe\Forms\FormField
     */
    protected function getFieldObject(string $field_name)
    {
        $fieldObject = null;
        $all_relations = array_merge(
            array_keys($this->obj->hasMany()),
            array_keys($this->obj->manyMany())
        );

        // @todo Pass localized title
        if ($this->fieldClasses && isset($this->fieldClasses[$field_name])) {
            $fieldClass = $this->fieldClasses[$field_name];
            $fieldObject = $fieldClass::create($field_name);
        }

        // If field object not pre-defined and not assotiation, try to
        // get default field
        if (empty($fieldObject) && !in_array($field_name, $all_relations)) {
            $fieldObject = $this
                ->obj
                ->dbObject($field_name)
                ->scaffoldFormField(null, $this->getParamsArray())
                ->setTitle($this->obj->fieldLabel($field_name));
        }

        // If field object not available and is an assotiation
        if (empty($fieldObject) && in_array($field_name, $all_relations)) {
            $fieldObject = $this->genertateAssotiationField($field_name);
        }

        // If still not field object has been generated and we are not dealing with a relation
        // generate a ReadonlyField
        if (empty($fieldObject) && !in_array($field_name, $all_relations)) {
            $fieldObject = ReadonlyField::create(
                $field_name,
                $this->obj->fieldLabel($field_name)
            );

            $value = $this->obj->obj($field_name);

            if (!empty($value)) {
                $fieldObject->setValue($value->getValue());
            }
        }

        return $fieldObject;
    }

    /**
     * Try to generate the relevent field for an assotiation on the current object
     *
     * @param string $field_name The current db field name
     *
     * @return GridField|null
     */
    protected function genertateAssotiationField(string $field_name)
    {
        $has_many = $this->obj->hasMany();
        $many_many = $this->obj->manyMany();
        $field_class = GridField::class;

        // If relations disabled or object no saved
        if (!$this->obj->isInDB()) {
            return;
        }

        // If field class has been pre-configured, use custom class
        if (isset($this->fieldClasses[$field_name])) {
            $field_class = $this->fieldClasses[$field_name];
        }

        /** @var GridField $grid */
        return Injector::inst()->create(
            $field_class,
            $field_name,
            $this->obj->fieldLabel($field_name),
            $this->obj->$field_name(),
            GridFieldConfig_RelationEditor::create()
        );
    }

    /**
     * Push the selected field into the chosen list
     *
     * @return FieldList
     */
    protected function addField(FormField $field, FieldList $fields)
    {
        if ($this->tabbed && empty($this->curr_tab)) {
            throw new LogicException('No tab set for field ' . $field->getName());
        }

        if ($this->tabbed) {
            $fields->addFieldToTab($this->curr_tab, $field);
        } else {
            $fields->push($field);
        }

        return $fields;
    }
}

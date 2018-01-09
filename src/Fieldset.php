<?php

namespace SDekkers\FormBuilder;

class Fieldset
{

    /**
     * @var  Fieldset
     */
    protected static $_instance;

    /**
     * @var  array  contains references to all instantiations of Fieldset
     */
    protected static $_instances = [];
    /**
     * @var  string  instance id
     */
    protected $name;
    /**
     * @var  string  tag used to wrap this instance
     */
    protected $fieldset_tag = null;
    /**
     * @var  Fieldset  instance to which this instance belongs
     */
    protected $fieldset_parent = null;
    /**
     * @var  array  instances that belong to this one
     */
    protected $fieldset_children = [];
    /**
     * @var  array  array of FieldsetField objects
     */
    protected $fields = [];
    /**
     * @var  Form  instance of form
     */
    protected $form;
    /**
     * @var  array  configuration array
     */
    protected $config = [];
    /**
     * @var  array  disabled fields array
     */
    protected $disabled = [];

    /**
     * Object constructor
     *
     * @param  string
     * @param  array
     */
    public function __construct($name = '', array $config = [])
    {
        // support new Fieldset($config) syntax
        if (is_array($name)) {
            $config = $name;
            $name = '';
        }

        if (isset($config['form_instance'])) {
            $this->form($config['form_instance']);
            unset($config['form_instance']);
        }

        $this->name = (string)$name;
        $this->config = $config;
    }

    /**
     * Get related Form instance or create it
     *
     * @param   bool|Form $instance
     *
     * @return  Form
     */
    public function form($instance = true)
    {
        if ($instance instanceof Form) {
            $this->form = $instance;

            return $instance;
        }

        if (empty($this->form) and $instance === true) {
            $this->form = Form::forge($this);
        }

        return $this->form;
    }

    /**
     * Create Fieldset object
     *
     * @param   string $name Identifier for this fieldset
     * @param   array $config Configuration array
     *
     * @return  Fieldset
     */
    public static function forge($name = '__default__', array $config = [])
    {
        if ($exists = static::instance($name)) {
            return $exists;
        }

        static::$_instances[$name] = new static($name, $config);

        if ($name == '__default__') {
            static::$_instance = static::$_instances[$name];
        }

        return static::$_instances[$name];
    }

    /**
     * Return a specific instance, or the default instance (is created if necessary)
     *
     * @param   Fieldset $instance
     *
     * @return  Fieldset
     */
    public static function instance($instance = null)
    {
        if ($instance !== null) {
            if (!array_key_exists($instance, static::$_instances)) {
                return false;
            }

            return static::$_instances[$instance];
        }

        if (static::$_instance === null) {
            static::$_instance = static::forge();
        }

        return static::$_instance;
    }

    /**
     * Set the tag to be used for this fieldset
     *
     * @param  string $tag
     *
     * @return  Fieldset       this, to allow chaining
     */
    public function set_fieldset_tag($tag)
    {
        $this->fieldset_tag = $tag;

        return $this;
    }

    /**
     * Set the parent Fieldset instance
     *
     * @param   Fieldset $fieldset parent fieldset to which this belongs
     *
     * @return  Fieldset
     */
    public function set_parent(Fieldset $fieldset)
    {
        if (!empty($this->fieldset_parent)) {
            throw new \Exception('Fieldset already has a parent, belongs to "' . $this->parent()->name . '".');
        }

        $children = $fieldset->children();
        while ($child = array_shift($children)) {
            if ($child === $this) {
                throw new \Exception('Circular reference detected, adding a Fieldset that\'s already a child as a parent.');
            }
            $children = array_merge($child->children(), $children);
        }

        $this->fieldset_parent = $fieldset;
        $fieldset->add_child($this);

        return $this;
    }

    /**
     * Return the child fieldset instances
     *
     * @return  array
     */
    public function children()
    {
        return $this->fieldset_children;
    }

    /**
     * Add a child Fieldset instance
     *
     * @param   Fieldset $fieldset
     *
     * @return  Fieldset
     */
    protected function add_child(Fieldset $fieldset)
    {
        if (is_null($fieldset->fieldset_tag)) {
            $fieldset->fieldset_tag = 'fieldset';
        }

        $this->fieldset_children[$fieldset->name] = $fieldset;

        return $this;
    }

    /**
     * Factory for FieldsetField objects
     *
     * @param $name
     * @param string $label
     * @param array $attributes
     * @return bool|false|mixed|FieldsetField
     * @throws \Exception
     */
    public function add($name, $label = '', array $attributes = [])
    {
        if ($name instanceof FieldsetField) {
            if ($name->name == '' or $this->field($name->name) !== false) {
                throw new \Exception('Fieldname empty or already exists in this Fieldset: "' . $name->name . '".');
            }

            $name->set_fieldset($this);
            $this->fields[$name->name] = $name;

            return $name;
        } elseif ($name instanceof Fieldset) {
            return false;
        }

        if (empty($name) || (is_array($name) and empty($name['name']))) {
            throw new \Exception('Cannot create field without name.');
        }

        // Allow passing the whole config in an array, will overwrite other values if that's the case
        if (is_array($name)) {
            $attributes = $name;
            $label = isset($name['label']) ? $name['label'] : '';
            $name = $name['name'];
        }

        // Check if it exists already, if so: return and give notice
        if ($field = $this->field($name)) {
            return $field;
        }

        $this->fields[$name] = new FieldsetField($name, $label, $attributes, $this);

        return $this->fields[$name];
    }

    /**
     * Get Field instance
     *
     * @param   string|null $name field name or null to fetch an array of all
     *
     * @return  FieldsetField|false  returns false when field wasn't found
     */
    public function field($name = null)
    {
        if ($name === null) {
            $fields = $this->fields;

            return $fields;
        }

        if (!array_key_exists($name, $this->fields)) {

            return false;
        }

        return $this->fields[$name];
    }

    /**
     * Delete a field instance
     *
     * @param   string  field name or null to fetch an array of all
     *
     * @return  Fieldset  this fieldset, for chaining
     */
    public function delete($name)
    {
        if (isset($this->fields[$name])) {
            unset($this->fields[$name]);
        }

        return $this;
    }

    /**
     * Add a model's fields
     * The model must have a method "set_form_fields" that takes this Fieldset instance
     * and adds fields to it.
     *
     * @param   string|Object $class either a full classname (including full namespace) or object instance
     * @param   array|Object $instance array or object that has the exactly same named properties to populate the
     *                                  fields
     * @param   string $method method name to call on model for field fetching
     *
     * @return  Fieldset       this, to allow chaining
     */
    public function add_model($class, $instance = null)
    {

        $this->fields = $class::set_form_fields($class, $instance)->fields;

        return $this;
    }

    /**
     * Populate the form's values using an input array or object
     *
     * @param   array|object $input
     * @param   bool $repopulate
     *
     * @return  Fieldset  this, to allow chaining
     */
    public function populate($input, $repopulate = false)
    {
        $fields = $this->field(null, true, false);
        foreach ($fields as $f) {
            if (is_array($input)) {
                // convert form field array's to dotted notation
                $name = str_replace(['[', ']'], ['.', ''], $f->name);

                // fetch the value for this field, and set it if found
                $value = array_key_exists($name, $input) ? $input[$name] : null;
                $value === null and $value = array_key_exists($f->basename, $input) ? $input[$f->basename] : null;
                $value !== null and $f->set_value($value, true);
            } elseif (is_object($input) and property_exists($input, $f->basename)) {
                $f->set_value($input->{$f->basename}, true);
            }
        }

        // Optionally overwrite values using post/get
        if ($repopulate) {
            $this->repopulate();
        }

        return $this;
    }

    /**
     * Set all fields to the input from get or post (depends on the form method attribute)
     *
     * @return  Fieldset      this, to allow chaining
     */
    public function repopulate()
    {
        $fields = $this->field(null, true);
        foreach ($fields as $f) {
            // Don't repopulate the CSRF field
            if ($f->name === '_token') {
                continue;
            }

            if (($value = $f->input()) !== null) {
                $f->set_value($value, true);
            }
        }

        return $this;
    }

    /**
     * Enable a disabled field from being build
     *
     * @param   mixed $name
     *
     * @return  Fieldset      this, to allow chaining
     */
    public function enable($name = null)
    {
        // Check if it exists. if not, bail out
        if (!$this->field($name)) {
            throw new \Exception('Field "' . $name . '" does not exist in this Fieldset.');
        }

        if (isset($this->disabled[$name])) {
            unset($this->disabled[$name]);
        }

        return $this;
    }

    /**
     * Disable a field from being build
     *
     * @param null $name
     * @return $this
     * @throws \Exception
     */
    public function disable($name = null)
    {
        // Check if it exists. if not, bail out
        if (!$this->field($name)) {
            throw new \Exception('Field "' . $name . '" does not exist in this Fieldset.');
        }

        isset($this->disabled[$name]) or $this->disabled[$name] = $name;

        return $this;
    }

    /**
     * Magic method toString that will build this as a form
     *
     * @return  string
     */
    public function __toString()
    {
        return $this->build();
    }

    /**
     * Build the fieldset HTML
     *
     * @param   mixed $action
     *
     * @return  string
     */
    public function build($action = null)
    {
        $attributes = $this->get_config('form_attributes');
        if ($action and ($this->fieldset_tag == 'form' or empty($this->fieldset_tag))) {
            $attributes['action'] = $action;
        }

        $open = ($this->fieldset_tag == 'form' or empty($this->fieldset_tag)) ?
            $this->form()->open($attributes) . PHP_EOL : $this->form()->{$this->fieldset_tag . '_open'}($attributes);

        $fields_output = '';

        foreach ($this->field() as $f) {
            in_array($f->name, $this->disabled) or $fields_output .= $f->build() . PHP_EOL;
        }

        $close = ($this->fieldset_tag == 'form' or empty($this->fieldset_tag)) ?
            $this->form()->close($attributes) . PHP_EOL : $this->form()->{$this->fieldset_tag . '_close'}($attributes);

        $template = $this->form()->get_config((empty($this->fieldset_tag) ? 'form' : $this->fieldset_tag) . '_template',
            "\n\t\t{open}\n\t\t<table>\n{fields}\n\t\t</table>\n\t\t{close}\n");

        $template = str_replace(['{form_open}', '{open}', '{fields}', '{form_close}', '{close}'],
            [$open, $open, $fields_output, $close, $close],
            $template);

        return $template;
    }

    /**
     *  Get a single or multiple config values by key
     *
     * @param null $key
     * @param null $default
     * @return array|mixed|null|string
     */
    public function get_config($key = null, $default = null)
    {
        if ($key === null) {
            return $this->config;
        }

        if (is_array($key)) {
            $output = [];
            foreach ($key as $k) {
                $output[$k] = $this->get_config($k, $default);
            }

            return $output;
        }

        if (strpos($key, '.') === false) {
            return array_key_exists($key, $this->config) ? $this->config[$key] : $default;
        } else {
            return array_key_exists($key, $this->config) ? $this->config[$key] : $default;
        }
    }

    /**
     * Sets a config value on the fieldset
     *
     * @param array $config
     * @return $this
     */
    public function set_config(array $config)
    {
        foreach ($config as $key => $value) {
            $this->config[$key] = $value;
        }

        return $this;
    }

    /**
     * Get the fieldset name
     *
     * @return string
     */
    public function get_name()
    {
        return $this->name;
    }
}

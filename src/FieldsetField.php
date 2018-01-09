<?php

namespace SDekkers\FormBuilder;

/**
 * Fieldset Class
 *
 * Define a set of fields that can be used to generate a form.
 *
 * @package   Fuel
 * @category  Core
 */
class FieldsetField
{

    /**
     * @var  Fieldset  Fieldset this field belongs to
     */
    protected $fieldset;

    /**
     * @var  string  Name of this field
     */
    protected $name = '';

    /**
     * @var  string  Base name of this field
     */
    protected $basename = '';

    /**
     * @var  string  Field type for form generation, false to prevent it showing
     */
    protected $type = 'text';

    /**
     * @var  string  Field label for form label generation
     */
    protected $label = '';

    /**
     * @var  mixed  (Default) value of this field
     */
    protected $value;

    /**
     * @var  string  Description text to show with the field
     */
    protected $description = '';

    /**
     * @var  array  Attributes for form generation
     */
    protected $attributes = [];

    /**
     * @var  array  Options, only available for select, radio & checkbox types
     */
    protected $options = [];

    /**
     * @var  string  Template for form building
     */
    protected $template;

    /**
     * Constructor
     *
     * @param  string $name
     * @param  string $label
     * @param  array $attributes
     * @param  Fieldset $fieldset
     *
     * @throws \RuntimeException
     */
    public function __construct($name, $label = '', array $attributes = [], $fieldset = null)
    {
        $this->name = (string)$name;

        if ($this->name === "") {
            throw new \RuntimeException('Fieldset field name may not be empty.');
        }

        // determine the field's base name (for fields with array indices)
        $this->basename = ($pos = strpos($this->name, '[')) ? rtrim(substr(strrchr($this->name, '['), 1), ']') :
            $this->name;

        $this->fieldset = $fieldset instanceof Fieldset ? $fieldset : null;

        // Don't allow name in attributes
        unset($attributes['name']);

        // Use specific setter when available
        foreach ($attributes as $attr => $val) {
            if (method_exists($this, $method = 'set_' . $attr)) {
                $this->{$method}($val);
                unset($attributes[$attr]);
            }
        }

        // Add default "type" attribute if not specified
        empty($attributes['type']) and $this->set_type($this->type);

        // only when non-empty, will supersede what was given in $attributes
        $label and $this->set_label($label);

        $this->attributes = array_merge($this->attributes, $attributes);
    }

    /**
     * Change the field type for form generation
     *
     * @param   string $type
     *
     * @return  FieldsetField  this, to allow chaining
     */
    public function set_type($type)
    {
        $this->type = $type;
        $this->set_attribute('type', $type);

        return $this;
    }

    /**
     * Sets an attribute on the field
     *
     * @param   string
     * @param   mixed   new value or null to unset
     *
     * @return  FieldsetField  this, to allow chaining
     */
    public function set_attribute($attr, $value = null)
    {
        $attr = is_array($attr) ? $attr : [$attr => $value];
        foreach ($attr as $key => $value) {
            if ($value === null) {
                unset($this->attributes[$key]);
            } else {
                $this->attributes[$key] = $value;
            }
        }

        return $this;
    }

    /**
     * Change the field label
     *
     * @param   string $label
     *
     * @return  FieldsetField  this, to allow chaining
     */
    public function set_label($label)
    {
        $this->label = $label;
        $this->set_attribute('label', $label);

        return $this;
    }

    /**
     * @param Fieldset $fieldset
     * @return $this
     * @throws \Exception
     */
    public function set_fieldset(Fieldset $fieldset)
    {
        // if we currently have a fieldset
        if ($this->fieldset) {
            // remove the field from the fieldset
            $this->fieldset->delete($this->name);

            // reset the fieldset
            $this->fieldset = null;

            // add this field to the new fieldset
            $fieldset->add($this);
        }

        // assign the new fieldset
        $this->fieldset = $fieldset;

        return $this;
    }

    /**
     * Change the field's current or default value
     *
     * @param   string $value
     * @param   bool $repopulate
     *
     * @return  FieldsetField  this, to allow chaining
     */
    public function set_value($value, $repopulate = false)
    {
        // Repopulation is handled slightly different in some cases
        if ($repopulate) {
            if (($this->type == 'radio' or $this->type == 'checkbox') and empty($this->options)) {
                if ($this->value == $value) {
                    $this->set_attribute('checked', 'checked');
                }

                return $this;
            }
        }

        $this->value = $value;
        $this->set_attribute('value', $value);

        return $this;
    }

    /**
     * Change the field description
     *
     * @param   string $description
     *
     * @return  FieldsetField  this, to allow chaining
     */
    public function set_description($description)
    {
        $this->description = strval($description);

        return $this;
    }

    /**
     * Template the output
     *
     * @param   string $template
     *
     * @return  FieldsetField  this, to allow chaining
     */
    public function set_template($template = null)
    {
        $this->template = $template;

        return $this;
    }

    /**
     * Add an option value with label
     *
     * @param   string|array  one option value, or multiple value=>label pairs in an array
     * @param   string
     * @param   bool            Whether or not to replace the current options
     *
     * @return  FieldsetField  this, to allow chaining
     */
    public function set_options($value, $label = null, $replace_options = false)
    {
        if (!is_array($value)) {
            \Arr::set($this->options, $value, $label);

            return $this;
        }

        $merge = function (&$array, $new, $merge) {
            foreach ($new as $k => $v) {
                if (isset($array[$k]) and is_array($array[$k]) and is_array($v)) {
                    $merge($array[$k], $v);
                } else {
                    $array[$k] = $v;
                }
            }
        };

        ($replace_options or empty($this->options)) ? $this->options = $value : $merge($this->options, $value, $merge);

        return $this;
    }

    /**
     * Magic get method to allow getting class properties but still having them protected
     * to disallow writing.
     *
     * @return  mixed
     */
    public function __get($property)
    {
        return $this->$property;
    }

    /**
     * Build the field
     *
     * @return  string
     */
    public function __toString()
    {
        try {
            return $this->build();
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Build the field
     *
     * @return  string
     */
    public function build()
    {
        $form = $this->fieldset()->form();

        // Add IDs when auto-id is on
        if ($form->get_config('auto_id', false) === true and $this->get_attribute('id') == '') {
            $auto_id = $form->get_config('auto_id_prefix', '') . str_replace(['[', ']'], ['-', ''], $this->name);
            $this->set_attribute('id', $auto_id);
        }

        switch (!empty($this->attributes['tag']) ? $this->attributes['tag'] : $this->type) {
            case 'hidden':
                $build_field = $form->hidden($this->name, $this->value, $this->attributes);
                break;

            case 'radio':
            case 'checkbox':
                if ($this->options) {
                    $build_field = [];
                    $i = 0;
                    foreach ($this->options as $value => $label) {
                        $attributes = $this->attributes;
                        $attributes['name'] = $this->name;
                        $this->type == 'checkbox' and $attributes['name'] .= '[' . $i . ']';

                        $attributes['value'] = $value;
                        $attributes['label'] = $label;

                        if (is_array($this->value) ? in_array($value, $this->value) : $value == $this->value) {
                            $attributes['checked'] = 'checked';
                        }

                        if (!empty($attributes['id'])) {
                            $attributes['id'] .= '_' . $i;
                        } else {
                            $attributes['id'] = null;
                        }
                        $build_field[$form->label($label,
                            null,
                            ['for' => $attributes['id']])] = $this->type == 'radio' ? $form->radio($attributes) :
                            $form->checkbox($attributes);

                        $i++;
                    }
                } else {
                    $build_field = $this->type == 'radio' ? $form->radio($this->name, $this->value, $this->attributes) :
                        $form->checkbox($this->name, $this->value, $this->attributes);
                }
                break;

            case 'select':
                $attributes = $this->attributes;
                $name = $this->name;
                unset($attributes['type']);
                array_key_exists('multiple', $attributes) and $name .= '[]';
                $build_field = $form->select($name, $this->value, $this->options, $attributes);
                break;

            case 'textarea':
                $attributes = $this->attributes;
                unset($attributes['type']);
                $build_field = $form->textarea($this->name, $this->value, $attributes);
                break;

            case 'button':
                $build_field = $form->button($this->name, $this->value, $this->attributes);
                break;

            case false:
                $build_field = '';
                break;

            default:
                $build_field = $form->input($this->name, $this->value, $this->attributes);
                break;
        }

        if (empty($build_field) or $this->type == 'hidden') {
            return $build_field;
        }

        return $this->template($build_field);
    }

    /**
     * Return the parent Fieldset object
     *
     * @return  Fieldset
     */
    public function fieldset()
    {
        return $this->fieldset;
    }

    /**
     * Get a single or multiple attributes by key
     *
     * @param   string|array  a single key or multiple in an array, empty to fetch all
     * @param   mixed         default output when attribute wasn't set
     *
     * @return  mixed|array   a single attribute or multiple in an array when $key input was an array
     */
    public function get_attribute($key = null, $default = null)
    {
        if ($key === null) {
            return $this->attributes;
        }

        if (is_array($key)) {
            $output = [];
            foreach ($key as $k) {
                $output[$k] = array_key_exists($k, $this->attributes) ? $this->attributes[$k] : $default;
            }

            return $output;
        }

        return array_key_exists($key, $this->attributes) ? $this->attributes[$key] : $default;
    }

    protected function template($build_field)
    {
        $form = $this->fieldset()->form();

        $required_mark = $this->get_attribute('required', null) ? $form->get_config('required_mark', null) : null;
        $label = $this->label ?
            $form->label($this->label,
                null,
                [
                    'id' => 'label_' . $this->name,
                    'for' => $this->get_attribute('id', null),
                    'class' => $form->get_config('label_class', null)
                ]) : '';

        if (is_array($build_field)) {
            $label = $this->label ?
                str_replace('{label}', $this->label, $form->get_config('group_label', '<span>{label}</span>')) : '';
            $template = $this->template ?:
                $form->get_config('multi_field_template',
                    "\t\t<tr>\n\t\t\t<td>{group_label}{required}</td>\n\t\t\t<td>{fields}\n\t\t\t\t{field} {label}<br />\n{fields}\t\t\t\n\t\t\t</td>\n\t\t</tr>\n");
            if ($template && preg_match('#\{fields\}(.*)\{fields\}#Dus', $template, $match) > 0) {
                $build_fields = '';
                foreach ($build_field as $lbl => $bf) {
                    $bf_temp = str_replace('{label}', $lbl, $match[1]);
                    $bf_temp = str_replace('{required}', $required_mark, $bf_temp);
                    $bf_temp = str_replace('{field}', $bf, $bf_temp);
                    $build_fields .= $bf_temp;
                }

                $template = str_replace($match[0], '{fields}', $template);
                $template = str_replace([
                    '{group_label}',
                    '{required}',
                    '{fields}',
                    '{description}'
                ],
                    [$label, $required_mark, $build_fields, $this->description],
                    $template);

                return $template;
            }

            // still here? wasn't a multi field template available, try the normal one with imploded $build_field
            $build_field = implode(' ', $build_field);
        }

        // determine the field_id, which allows us to identify the field for CSS purposes
        $field_id = 'col_' . $this->name;

        $template = $this->template ?:
            $form->get_config('field_template',
                "\t\t<tr>\n\t\t\t<td>{label}{required}</td>\n\t\t\t<td>{field} {description}</td>\n\t\t</tr>\n");
        $template = str_replace([
            '{label}',
            '{required}',
            '{field}',
            '{description}',
            '{field_id}'
        ],
            [$label, $required_mark, $build_field, $this->description, $field_id],
            $template);

        return $template;
    }

    /**
     * Alias for $this->fieldset->add() to allow chaining
     *
     * @param $name
     * @param string $label
     * @param array $attributes
     * @return FieldsetField
     * @throws \Exception
     */
    public function add($name, $label = '', array $attributes = [])
    {
        return $this->fieldset()->add($name, $label, $attributes);
    }
}

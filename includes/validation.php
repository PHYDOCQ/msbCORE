<?php

class Validator {
    private $errors = [];
    private $data = [];
    
    public function __construct($data = []) {
        $this->data = $data;
        $this->errors = [];
    }
    
    public function required($field, $message = null) {
        if (!isset($this->data[$field]) || empty(trim($this->data[$field]))) {
            $this->errors[$field][] = $message ?? "Field {$field} is required";
        }
        return $this;
    }
    
    public function email($field, $message = null) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
                $this->errors[$field][] = $message ?? "Field {$field} must be a valid email";
            }
        }
        return $this;
    }
    
    public function min($field, $length, $message = null) {
        if (isset($this->data[$field]) && strlen($this->data[$field]) < $length) {
            $this->errors[$field][] = $message ?? "Field {$field} must be at least {$length} characters";
        }
        return $this;
    }
    
    public function max($field, $length, $message = null) {
        if (isset($this->data[$field]) && strlen($this->data[$field]) > $length) {
            $this->errors[$field][] = $message ?? "Field {$field} must not exceed {$length} characters";
        }
        return $this;
    }
    
    public function numeric($field, $message = null) {
        if (isset($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field][] = $message ?? "Field {$field} must be numeric";
        }
        return $this;
    }
    
    public function date($field, $format = 'Y-m-d', $message = null) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $d = DateTime::createFromFormat($format, $this->data[$field]);
            if (!$d || $d->format($format) !== $this->data[$field]) {
                $this->errors[$field][] = $message ?? "Field {$field} must be a valid date";
            }
        }
        return $this;
    }
    
    public function phone($field, $message = null) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $phone = preg_replace('/[^0-9]/', '', $this->data[$field]);
            if (strlen($phone) < 10 || strlen($phone) > 15) {
                $this->errors[$field][] = $message ?? "Field {$field} must be a valid phone number";
            }
        }
        return $this;
    }
    
    public function unique($field, $table, $column = null, $excludeId = null, $message = null) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $column = $column ?? $field;
            $db = Database::getInstance()->getConnection();
            
            $query = "SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?";
            $params = [$this->data[$field]];
            
            if ($excludeId) {
                $query .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                $this->errors[$field][] = $message ?? "Field {$field} must be unique";
            }
        }
        return $this;
    }
    
    public function exists($field, $table, $column = null, $message = null) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $column = $column ?? 'id';
            $db = Database::getInstance()->getConnection();
            
            $query = "SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$this->data[$field]]);
            $result = $stmt->fetch();
            
            if ($result['count'] === 0) {
                $this->errors[$field][] = $message ?? "Field {$field} does not exist";
            }
        }
        return $this;
    }
    
    public function in($field, $values, $message = null) {
        if (isset($this->data[$field]) && !in_array($this->data[$field], $values)) {
            $this->errors[$field][] = $message ?? "Field {$field} must be one of: " . implode(', ', $values);
        }
        return $this;
    }
    
    public function between($field, $min, $max, $message = null) {
        if (isset($this->data[$field])) {
            $value = is_numeric($this->data[$field]) ? (float)$this->data[$field] : strlen($this->data[$field]);
            if ($value < $min || $value > $max) {
                $this->errors[$field][] = $message ?? "Field {$field} must be between {$min} and {$max}";
            }
        }
        return $this;
    }
    
    public function regex($field, $pattern, $message = null) {
        if (isset($this->data[$field]) && !preg_match($pattern, $this->data[$field])) {
            $this->errors[$field][] = $message ?? "Field {$field} format is invalid";
        }
        return $this;
    }
    
    public function custom($field, $callback, $message = null) {
        if (isset($this->data[$field])) {
            $result = call_user_func($callback, $this->data[$field]);
            if (!$result) {
                $this->errors[$field][] = $message ?? "Field {$field} validation failed";
            }
        }
        return $this;
    }
    
    public function sometimes($field, $callback) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $callback($this);
        }
        return $this;
    }
    
    public function isValid() {
        return empty($this->errors);
    }
    
    public function getErrors() {
        return $this->errors;
    }
    
    public function getFirstError($field = null) {
        if ($field) {
            return isset($this->errors[$field]) ? $this->errors[$field][0] : null;
        }
        
        foreach ($this->errors as $fieldErrors) {
            if (!empty($fieldErrors)) {
                return $fieldErrors[0];
            }
        }
        
        return null;
    }
    
    public function addError($field, $message) {
        $this->errors[$field][] = $message;
        return $this;
    }
    
    public function getValidatedData() {
        $validData = [];
        foreach ($this->data as $key => $value) {
            if (!isset($this->errors[$key])) {
                $validData[$key] = Security::sanitizeInput($value);
            }
        }
        return $validData;
    }
    
    // Static methods for quick validation
    public static function validateWorkOrder($data) {
        $validator = new self($data);
        
        return $validator
            ->required('vehicle_id', 'Vehicle is required')
            ->exists('vehicle_id', 'vehicles', 'id', 'Selected vehicle does not exist')
            ->required('damage_description', 'Damage description is required')
            ->min('damage_description', 10, 'Damage description must be at least 10 characters')
            ->sometimes('technician_id', function($v) {
                $v->exists('technician_id', 'users', 'id', 'Selected technician does not exist');
            })
            ->in('priority', ['low', 'normal', 'high', 'urgent'], 'Invalid priority level')
            ->sometimes('estimated_cost', function($v) {
                $v->numeric('estimated_cost', 'Estimated cost must be numeric')
                  ->between('estimated_cost', 0, 999999999, 'Estimated cost must be between 0 and 999,999,999');
            })
            ->sometimes('estimated_start_date', function($v) {
                $v->date('estimated_start_date', 'Y-m-d H:i', 'Invalid estimated start date');
            })
            ->sometimes('estimated_completion_date', function($v) {
                $v->date('estimated_completion_date', 'Y-m-d H:i', 'Invalid estimated completion date');
            });
    }
    
    public static function validateCustomer($data, $excludeId = null) {
        $validator = new self($data);
        
        return $validator
            ->required('name', 'Customer name is required')
            ->min('name', 2, 'Customer name must be at least 2 characters')
            ->max('name', 100, 'Customer name must not exceed 100 characters')
            ->required('phone', 'Phone number is required')
            ->phone('phone', 'Invalid phone number format')
            ->unique('phone', 'customers', 'phone', $excludeId, 'Phone number already exists')
            ->sometimes('email', function($v) use ($excludeId) {
                $v->email('email', 'Invalid email format')
                  ->unique('email', 'customers', 'email', $excludeId, 'Email already exists');
            })
            ->in('customer_type', ['individual', 'corporate', 'insurance'], 'Invalid customer type')
            ->max('address', 500, 'Address must not exceed 500 characters')
            ->max('city', 50, 'City must not exceed 50 characters')
            ->sometimes('postal_code', function($v) {
                $v->regex('postal_code', '/^\d{5}$/', 'Postal code must be 5 digits');
            });
    }
    
    public static function validateVehicle($data) {
        $validator = new self($data);
        
        return $validator
            ->required('customer_id', 'Customer is required')
            ->exists('customer_id', 'customers', 'id', 'Selected customer does not exist')
            ->required('brand', 'Vehicle brand is required')
            ->max('brand', 50, 'Brand must not exceed 50 characters')
            ->required('model', 'Vehicle model is required')
            ->max('model', 50, 'Model must not exceed 50 characters')
            ->required('license_plate', 'License plate is required')
            ->unique('license_plate', 'vehicles', 'license_plate', null, 'License plate already exists')
            ->sometimes('year', function($v) {
                $v->numeric('year', 'Year must be numeric')
                  ->between('year', 1900, date('Y') + 1, 'Invalid year');
            })
            ->sometimes('transmission', function($v) {
                $v->in('transmission', ['manual', 'automatic', 'cvt'], 'Invalid transmission type');
            });
    }
    
    public static function validateInventory($data, $excludeId = null) {
        $validator = new self($data);
        
        return $validator
            ->required('category_id', 'Category is required')
            ->exists('category_id', 'inventory_categories', 'id', 'Selected category does not exist')
            ->required('name', 'Item name is required')
            ->min('name', 2, 'Item name must be at least 2 characters')
            ->max('name', 100, 'Item name must not exceed 100 characters')
            ->sometimes('item_code', function($v) use ($excludeId) {
                $v->unique('item_code', 'inventory', 'item_code', $excludeId, 'Item code already exists');
            })
            ->numeric('minimum_stock', 'Minimum stock must be numeric')
            ->between('minimum_stock', 0, 999999, 'Minimum stock must be between 0 and 999,999')
            ->sometimes('unit_cost', function($v) {
                $v->numeric('unit_cost', 'Unit cost must be numeric')
                  ->between('unit_cost', 0, 999999999, 'Unit cost must be between 0 and 999,999,999');
            })
            ->in('unit_of_measure', ['pcs', 'liter', 'kg', 'meter', 'box', 'roll'], 'Invalid unit of measure');
    }
}

// Helper function for quick validation
function validate($data, $rules) {
    $validator = new Validator($data);
    
    foreach ($rules as $field => $fieldRules) {
        foreach ($fieldRules as $rule) {
            if (is_string($rule)) {
                $validator->$rule($field);
            } elseif (is_array($rule)) {
                $method = array_shift($rule);
                $validator->$method($field, ...$rule);
            }
        }
    }
    
    return $validator;
}
?>

<?php

class Validators {
    
    /**
     * Validate wrestler data
     */
    public static function validateWrestler(array $data, bool $isUpdate = false): array {
        $errors = [];
        
        // Required fields for creation
        if (!$isUpdate || isset($data['name'])) {
            if (empty($data['name'])) {
                $errors['name'] = 'Name is required';
            } elseif (strlen($data['name']) > 255) {
                $errors['name'] = 'Name must be 255 characters or less';
            }
        }
        
        // Generate slug from name if not provided
        if (!empty($data['name']) && empty($data['slug'])) {
            $data['slug'] = Security::generateSlug($data['name']);
        }
        
        // Validate slug
        if (isset($data['slug'])) {
            if (!Security::validateSlug($data['slug'])) {
                $errors['slug'] = 'Slug must be lowercase letters, numbers and hyphens only';
            } elseif (strlen($data['slug']) > 100) {
                $errors['slug'] = 'Slug must be 100 characters or less';
            }
        }
        
        // Validate boolean fields
        if (isset($data['active'])) {
            $data['active'] = filter_var($data['active'], FILTER_VALIDATE_BOOLEAN);
        }
        
        // Validate numeric fields
        $numericFields = ['record_wins', 'record_losses', 'record_draws', 'elo', 'points'];
        foreach ($numericFields as $field) {
            if (isset($data[$field])) {
                if (!is_numeric($data[$field]) || $data[$field] < 0) {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must be a non-negative number';
                }
                $data[$field] = (int) $data[$field];
            }
        }
        
        // Validate URLs
        if (isset($data['profile_img_url']) && !empty($data['profile_img_url'])) {
            if (!Security::validateUrl($data['profile_img_url'])) {
                $errors['profile_img_url'] = 'Profile image URL is invalid';
            }
        }
        
        return ['data' => $data, 'errors' => $errors];
    }
    
    /**
     * Validate company data
     */
    public static function validateCompany(array $data, bool $isUpdate = false): array {
        $errors = [];
        
        // Required fields
        if (!$isUpdate || isset($data['name'])) {
            if (empty($data['name'])) {
                $errors['name'] = 'Name is required';
            } elseif (strlen($data['name']) > 255) {
                $errors['name'] = 'Name must be 255 characters or less';
            }
        }
        
        // Generate slug from name if not provided
        if (!empty($data['name']) && empty($data['slug'])) {
            $data['slug'] = Security::generateSlug($data['name']);
        }
        
        // Validate slug
        if (isset($data['slug'])) {
            if (!Security::validateSlug($data['slug'])) {
                $errors['slug'] = 'Slug must be lowercase letters, numbers and hyphens only';
            } elseif (strlen($data['slug']) > 100) {
                $errors['slug'] = 'Slug must be 100 characters or less';
            }
        }
        
        // Validate boolean fields
        if (isset($data['active'])) {
            $data['active'] = filter_var($data['active'], FILTER_VALIDATE_BOOLEAN);
        }
        
        // Validate URLs
        $urlFields = ['logo_url', 'banner_url'];
        foreach ($urlFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                if (!Security::validateUrl($data[$field])) {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is invalid';
                }
            }
        }
        
        // Validate links JSON
        if (isset($data['links'])) {
            if (is_string($data['links'])) {
                if (!Security::validateJson($data['links'])) {
                    $errors['links'] = 'Links must be valid JSON';
                } else {
                    $data['links'] = Security::safeJsonDecode($data['links']);
                }
            }
            
            if (is_array($data['links'])) {
                // Validate URLs in links
                foreach ($data['links'] as $key => $url) {
                    if (!empty($url) && !Security::validateUrl($url)) {
                        $errors['links'] = "Invalid URL for {$key}";
                        break;
                    }
                }
                $data['links'] = Security::safeJsonEncode($data['links']);
            }
        }
        
        return ['data' => $data, 'errors' => $errors];
    }
    
    /**
     * Validate division data
     */
    public static function validateDivision(array $data, bool $isUpdate = false): array {
        $errors = [];
        
        // Required fields
        if (!$isUpdate || isset($data['name'])) {
            if (empty($data['name'])) {
                $errors['name'] = 'Name is required';
            } elseif (strlen($data['name']) > 255) {
                $errors['name'] = 'Name must be 255 characters or less';
            }
        }
        
        // Generate slug from name if not provided
        if (!empty($data['name']) && empty($data['slug'])) {
            $data['slug'] = Security::generateSlug($data['name']);
        }
        
        // Validate slug
        if (isset($data['slug'])) {
            if (!Security::validateSlug($data['slug'])) {
                $errors['slug'] = 'Slug must be lowercase letters, numbers and hyphens only';
            } elseif (strlen($data['slug']) > 100) {
                $errors['slug'] = 'Slug must be 100 characters or less';
            }
        }
        
        // Validate boolean fields
        if (isset($data['active'])) {
            $data['active'] = filter_var($data['active'], FILTER_VALIDATE_BOOLEAN);
        }
        
        // Validate eligibility JSON
        if (isset($data['eligibility'])) {
            if (is_string($data['eligibility'])) {
                if (!Security::validateJson($data['eligibility'])) {
                    $errors['eligibility'] = 'Eligibility must be valid JSON';
                } else {
                    $data['eligibility'] = Security::safeJsonDecode($data['eligibility']);
                }
            }
            
            if (is_array($data['eligibility'])) {
                // Validate eligibility structure
                if (isset($data['eligibility']['min_weight']) && !is_null($data['eligibility']['min_weight'])) {
                    if (!is_numeric($data['eligibility']['min_weight']) || $data['eligibility']['min_weight'] < 0) {
                        $errors['eligibility'] = 'Min weight must be a positive number';
                    }
                }
                
                if (isset($data['eligibility']['max_weight']) && !is_null($data['eligibility']['max_weight'])) {
                    if (!is_numeric($data['eligibility']['max_weight']) || $data['eligibility']['max_weight'] < 0) {
                        $errors['eligibility'] = 'Max weight must be a positive number';
                    }
                }
                
                if (isset($data['eligibility']['gender'])) {
                    $validGenders = ['male', 'female', 'any'];
                    if (!in_array($data['eligibility']['gender'], $validGenders)) {
                        $errors['eligibility'] = 'Gender must be one of: ' . implode(', ', $validGenders);
                    }
                }
                
                $data['eligibility'] = Security::safeJsonEncode($data['eligibility']);
            }
        }
        
        return ['data' => $data, 'errors' => $errors];
    }
    
    /**
     * Validate event data
     */
    public static function validateEvent(array $data, bool $isUpdate = false): array {
        $errors = [];
        
        // Required fields
        if (!$isUpdate || isset($data['name'])) {
            if (empty($data['name'])) {
                $errors['name'] = 'Name is required';
            } elseif (strlen($data['name']) > 255) {
                $errors['name'] = 'Name must be 255 characters or less';
            }
        }
        
        if (!$isUpdate || isset($data['company_id'])) {
            if (empty($data['company_id']) || !is_numeric($data['company_id'])) {
                $errors['company_id'] = 'Valid company ID is required';
            } else {
                $data['company_id'] = (int) $data['company_id'];
            }
        }
        
        if (!$isUpdate || isset($data['date'])) {
            if (empty($data['date'])) {
                $errors['date'] = 'Date is required';
            } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date'])) {
                $errors['date'] = 'Date must be in YYYY-MM-DD format';
            }
        }
        
        // Generate slug from name if not provided
        if (!empty($data['name']) && empty($data['slug'])) {
            $data['slug'] = Security::generateSlug($data['name']);
        }
        
        // Validate slug
        if (isset($data['slug'])) {
            if (!Security::validateSlug($data['slug'])) {
                $errors['slug'] = 'Slug must be lowercase letters, numbers and hyphens only';
            } elseif (strlen($data['slug']) > 100) {
                $errors['slug'] = 'Slug must be 100 characters or less';
            }
        }
        
        // Validate type
        if (isset($data['type'])) {
            $validTypes = ['event', 'pay-per-view', 'tv-show', 'house-show', 'special'];
            if (!in_array($data['type'], $validTypes)) {
                $errors['type'] = 'Type must be one of: ' . implode(', ', $validTypes);
            }
        }
        
        // Validate venue
        if (isset($data['venue']) && strlen($data['venue']) > 255) {
            $errors['venue'] = 'Venue must be 255 characters or less';
        }
        
        // Validate attendance
        if (isset($data['attendance'])) {
            if (!is_null($data['attendance'])) {
                if (!is_numeric($data['attendance']) || $data['attendance'] < 0) {
                    $errors['attendance'] = 'Attendance must be a non-negative number';
                }
                $data['attendance'] = (int) $data['attendance'];
            }
        }
        
        return ['data' => $data, 'errors' => $errors];
    }
    
    /**
     * Validate match data
     */
    public static function validateMatch(array $data, bool $isUpdate = false): array {
        $errors = [];
        
        // Required fields
        $requiredFields = ['event_id', 'company_id', 'wrestler1_id', 'wrestler2_id'];
        foreach ($requiredFields as $field) {
            if (!$isUpdate || isset($data[$field])) {
                if (empty($data[$field]) || !is_numeric($data[$field])) {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
                } else {
                    $data[$field] = (int) $data[$field];
                }
            }
        }
        
        // Validate wrestlers are different
        if (isset($data['wrestler1_id']) && isset($data['wrestler2_id'])) {
            if ($data['wrestler1_id'] === $data['wrestler2_id']) {
                $errors['wrestler2_id'] = 'Wrestlers must be different';
            }
        }
        
        // Generate slug if not provided
        if (empty($data['slug']) && !empty($data['wrestler1_id']) && !empty($data['wrestler2_id'])) {
            $db = DB::getInstance();
            $wrestler1 = $db->findById('wrestlers', $data['wrestler1_id']);
            $wrestler2 = $db->findById('wrestlers', $data['wrestler2_id']);
            
            if ($wrestler1 && $wrestler2) {
                $data['slug'] = Security::generateSlug($wrestler1['name'] . '-vs-' . $wrestler2['name']);
            }
        }
        
        // Validate slug
        if (isset($data['slug'])) {
            if (!Security::validateSlug($data['slug'])) {
                $errors['slug'] = 'Slug must be lowercase letters, numbers and hyphens only';
            } elseif (strlen($data['slug']) > 100) {
                $errors['slug'] = 'Slug must be 100 characters or less';
            }
        }
        
        // Validate optional foreign keys
        $optionalForeignKeys = ['division_id', 'championship_id'];
        foreach ($optionalForeignKeys as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                if (!is_numeric($data[$field])) {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must be a number';
                } else {
                    $data[$field] = (int) $data[$field];
                }
            }
        }
        
        // Validate boolean fields
        if (isset($data['is_championship'])) {
            $data['is_championship'] = filter_var($data['is_championship'], FILTER_VALIDATE_BOOLEAN);
        }
        
        // Validate result outcome
        if (isset($data['result_outcome'])) {
            $validOutcomes = ['win', 'loss', 'draw', 'no_contest'];
            if (!in_array($data['result_outcome'], $validOutcomes)) {
                $errors['result_outcome'] = 'Result outcome must be one of: ' . implode(', ', $validOutcomes);
            }
        }
        
        // Validate result method
        if (isset($data['result_method']) && strlen($data['result_method']) > 100) {
            $errors['result_method'] = 'Result method must be 100 characters or less';
        }
        
        // Validate result round
        if (isset($data['result_round'])) {
            if (!is_null($data['result_round'])) {
                if (!is_numeric($data['result_round']) || $data['result_round'] < 1 || $data['result_round'] > 255) {
                    $errors['result_round'] = 'Result round must be between 1 and 255';
                }
                $data['result_round'] = (int) $data['result_round'];
            }
        }
        
        // Validate judges JSON
        if (isset($data['judges'])) {
            if (is_string($data['judges'])) {
                if (!Security::validateJson($data['judges'])) {
                    $errors['judges'] = 'Judges must be valid JSON';
                } else {
                    $data['judges'] = Security::safeJsonDecode($data['judges']);
                }
            }
            
            if (is_array($data['judges'])) {
                $data['judges'] = Security::safeJsonEncode($data['judges']);
            }
        }
        
        return ['data' => $data, 'errors' => $errors];
    }
    
    /**
     * Validate tag data
     */
    public static function validateTag(array $data, bool $isUpdate = false): array {
        $errors = [];
        
        // Required fields
        if (!$isUpdate || isset($data['name'])) {
            if (empty($data['name'])) {
                $errors['name'] = 'Name is required';
            } elseif (strlen($data['name']) > 255) {
                $errors['name'] = 'Name must be 255 characters or less';
            }
        }
        
        // Generate slug from name if not provided
        if (!empty($data['name']) && empty($data['slug'])) {
            $data['slug'] = Security::generateSlug($data['name']);
        }
        
        // Validate slug
        if (isset($data['slug'])) {
            if (!Security::validateSlug($data['slug'])) {
                $errors['slug'] = 'Slug must be lowercase letters, numbers and hyphens only';
            } elseif (strlen($data['slug']) > 100) {
                $errors['slug'] = 'Slug must be 100 characters or less';
            }
        }
        
        return ['data' => $data, 'errors' => $errors];
    }
    
    /**
     * Validate pagination parameters
     */
    public static function validatePagination(array $params): array {
        $validated = [];
        
        // Page number
        $validated['page'] = max(1, (int) ($params['page'] ?? 1));
        
        // Limit
        $validated['limit'] = min(
            MAX_PAGE_SIZE,
            max(1, (int) ($params['limit'] ?? DEFAULT_PAGE_SIZE))
        );
        
        // Search
        if (!empty($params['search'])) {
            $validated['search'] = trim($params['search']);
        }
        
        // Order by
        if (!empty($params['order_by'])) {
            // Whitelist allowed columns to prevent SQL injection
            $allowedColumns = [
                'id', 'name', 'slug', 'active', 'created_at',
                'record_wins', 'record_losses', 'elo', 'points',
                'date', 'type', 'venue', 'attendance'
            ];
            
            if (in_array($params['order_by'], $allowedColumns)) {
                $validated['order_by'] = $params['order_by'];
                
                // Order direction
                $direction = strtoupper($params['order_direction'] ?? 'ASC');
                if (in_array($direction, ['ASC', 'DESC'])) {
                    $validated['order_direction'] = $direction;
                }
            }
        }
        
        return $validated;
    }
    
    /**
     * Validate foreign key existence
     */
    public static function validateForeignKeys(array $data, array $references): array {
        $errors = [];
        $db = DB::getInstance();
        
        foreach ($references as $field => $table) {
            if (isset($data[$field]) && !empty($data[$field])) {
                if (!$db->exists($table, 'id = :id', ['id' => $data[$field]])) {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' does not exist';
                }
            }
        }
        
        return $errors;
    }
}
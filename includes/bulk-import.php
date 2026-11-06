<?php
/**
 * Bulk upload of categories and nominees from a CSV file.
 *
 * Every row is checked before anything is written, and the rows are saved in one
 * transaction, so a file with a mistake in it never leaves half its rows behind.
 */

const BULK_MAX_BYTES = 2097152;
const BULK_MAX_ROWS = 1000;
const BULK_MAX_REPORTED_ERRORS = 50;

/**
 * Columns each upload accepts, and whether they must have a value.
 */
function bulkColumns($type) {
    if ($type === 'categories') {
        return [
            'name'          => ['required' => true, 'help' => 'The category name, e.g. Artist of the Year.'],
            'description'   => ['required' => false, 'help' => 'A short description.'],
            'display_order' => ['required' => false, 'help' => 'A whole number. Lower numbers show first.'],
            'access_code'   => ['required' => false, 'help' => 'Up to 20 letters, numbers or dashes. Leave blank to have one made for you.'],
            'status'        => ['required' => false, 'help' => 'active or inactive. Blank means active.'],
        ];
    }

    return [
        'category'    => ['required' => true, 'help' => 'The category name, exactly as it appears in the chosen event.'],
        'name'        => ['required' => true, 'help' => 'The nominee\'s name.'],
        'description' => ['required' => false, 'help' => 'A short description.'],
        'shortcode'   => ['required' => false, 'help' => 'Up to 20 letters, numbers or dashes. Leave blank to have one made for you.'],
        'status'      => ['required' => false, 'help' => 'active or inactive. Blank means active.'],
    ];
}

/**
 * Read an uploaded CSV into rows.
 *
 * @param array  $file    The $_FILES entry
 * @param array  $columns From bulkColumns()
 * @param array  $errors  Filled with anything wrong with the file as a whole
 * @return array Each item is ['row' => spreadsheet row number, 'data' => values by column]
 */
function readBulkCsv(array $file, array $columns, array &$errors) {
    $uploadError = $file['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Please choose a CSV file.';
        return [];
    }
    if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE || ($file['size'] ?? 0) > BULK_MAX_BYTES) {
        $errors[] = 'That file is too large. The limit is 2MB.';
        return [];
    }
    if ($uploadError !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        $errors[] = 'The file could not be uploaded. Please try again.';
        return [];
    }

    $bytes = (string) file_get_contents($file['tmp_name']);

    // Excel workbooks start with these bytes, whatever the file is named
    if (strncmp($bytes, 'PK', 2) === 0 || strncmp($bytes, "\xD0\xCF\x11\xE0", 4) === 0) {
        $errors[] = 'That is an Excel workbook. In Excel choose Save As, then CSV, and upload that file.';
        return [];
    }
    if (strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION)) !== 'csv' || strpos($bytes, "\0") !== false) {
        $errors[] = 'Please upload a .csv file. You can download the sample below to see the layout.';
        return [];
    }

    // Excel adds a marker to UTF-8 files, and older versions save in Windows-1252
    if (strncmp($bytes, "\xEF\xBB\xBF", 3) === 0) {
        $bytes = substr($bytes, 3);
    }
    if (!mb_check_encoding($bytes, 'UTF-8')) {
        $bytes = mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252');
    }

    // Some regional versions of Excel separate columns with semicolons
    $firstLine = strtok($bytes, "\r\n");
    $delimiter = ',';
    foreach ([';', "\t"] as $candidate) {
        if (substr_count((string) $firstLine, $candidate) > substr_count((string) $firstLine, $delimiter)) {
            $delimiter = $candidate;
        }
    }

    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $bytes);
    rewind($stream);

    $headings = fgetcsv($stream, 0, $delimiter, '"', '');
    if (!is_array($headings)) {
        fclose($stream);
        $errors[] = 'That file is empty.';
        return [];
    }

    $headings = array_map(function ($heading) {
        return str_replace([' ', '-'], '_', strtolower(trim((string) $heading)));
    }, $headings);

    $unknown = array_diff(array_filter($headings, 'strlen'), array_keys($columns));
    if ($unknown) {
        $errors[] = 'Unknown column: ' . implode(', ', $unknown) . '. The columns allowed are: ' . implode(', ', array_keys($columns)) . '.';
    }
    foreach ($columns as $name => $column) {
        if ($column['required'] && !in_array($name, $headings, true)) {
            $errors[] = 'The first row must be the column headings, and "' . $name . '" is missing.';
        }
    }
    if ($errors) {
        fclose($stream);
        return [];
    }

    $rows = [];
    $rowNumber = 1;
    while (($cells = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
        $rowNumber++;

        if (implode('', array_map('trim', array_map('strval', $cells))) === '') {
            continue;
        }

        $extra = array_slice($cells, count($headings));
        if (implode('', array_map('trim', array_map('strval', $extra))) !== '') {
            $errors[] = "Row $rowNumber has more values than there are columns. Text with a comma in it must be inside double quotes.";
            continue;
        }

        $data = array_fill_keys(array_keys($columns), '');
        foreach ($headings as $index => $heading) {
            if ($heading !== '') {
                $data[$heading] = trim((string) ($cells[$index] ?? ''));
            }
        }
        $rows[] = ['row' => $rowNumber, 'data' => $data];

        if (count($rows) > BULK_MAX_ROWS) {
            fclose($stream);
            $errors[] = 'That file has more than ' . BULK_MAX_ROWS . ' rows. Please split it into smaller files.';
            return [];
        }
    }
    fclose($stream);

    if (!$rows && !$errors) {
        $errors[] = 'That file has no rows to add under the headings.';
    }

    return $rows;
}

/**
 * The same slug the single add forms make, with a stand-in for names that have
 * no Latin letters, which would otherwise all share an empty slug.
 */
function bulkSlug($name) {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    return trim($slug, '-') === '' ? 'item-' . substr(md5($name), 0, 10) : $slug;
}

/**
 * Compare names the way a person would: stored names have & as &amp; and any
 * capitals should not matter.
 */
function bulkNameKey($name) {
    return mb_strtolower(trim(html_entity_decode($name, ENT_QUOTES, 'UTF-8')));
}

/**
 * Check a status cell, adding an error when it is not one of the allowed words.
 */
function bulkStatus($value, $rowNumber, array &$errors) {
    $status = strtolower($value);
    if ($status === '') {
        return 'active';
    }
    if ($status !== 'active' && $status !== 'inactive') {
        $errors[] = "Row $rowNumber: status must be active or inactive.";
    }
    return $status;
}

/**
 * Check a code cell, and that it is not already in use here or earlier in the file.
 *
 * @return string The code, ready to save, or '' when one should be generated
 */
function bulkCode($value, $label, $rowNumber, PDOStatement $exists, array &$seen, array &$errors) {
    if ($value === '') {
        return '';
    }
    if (!preg_match('/^[A-Za-z0-9-]{1,20}$/', $value)) {
        $errors[] = "Row $rowNumber: $label must be up to 20 letters, numbers or dashes.";
        return $value;
    }

    $key = strtoupper($value);
    $exists->execute([$value]);
    if ($exists->fetchColumn()) {
        $errors[] = "Row $rowNumber: $label $value is already in use.";
    } elseif (isset($seen[$key])) {
        $errors[] = "Row $rowNumber: $label $value is also used on row {$seen[$key]}.";
    }
    $seen[$key] = $rowNumber;

    return $value;
}

/**
 * Make a code nothing else is using.
 *
 * @param callable $make Returns a fresh random code
 */
function bulkUniqueCode(callable $make, PDOStatement $exists, array &$seen) {
    do {
        $code = $make();
        $exists->execute([$code]);
    } while ($exists->fetchColumn() || isset($seen[strtoupper($code)]));

    $seen[strtoupper($code)] = 0;
    return $code;
}

/**
 * Check category rows for an event.
 *
 * @return array The rows, ready to save
 */
function prepareBulkCategories(PDO $pdo, $eventId, array $rows, array &$errors) {
    $existing = $pdo->prepare("SELECT slug, name FROM categories WHERE event_id = ?");
    $existing->execute([$eventId]);
    $slugs = [];
    $names = [];
    foreach ($existing->fetchAll() as $category) {
        $slugs[$category['slug']] = 0;
        $names[bulkNameKey($category['name'])] = 0;
    }

    $codeExists = $pdo->prepare("SELECT 1 FROM categories WHERE access_code = ?");
    $seenCodes = [];
    $prepared = [];

    foreach ($rows as $item) {
        $row = $item['row'];
        $data = $item['data'];
        $name = sanitize($data['name']);
        $slug = bulkSlug($name);
        $key = bulkNameKey($name);

        if ($name === '') {
            $errors[] = "Row $row: the name is empty.";
        } elseif (mb_strlen($name) > 150) {
            $errors[] = "Row $row: the name is longer than 150 characters.";
        } elseif (isset($names[$key]) || isset($slugs[$slug])) {
            $where = ($names[$key] ?? $slugs[$slug]) ? 'is also on row ' . ($names[$key] ?? $slugs[$slug]) : 'is already in this event';
            $errors[] = "Row $row: the category \"" . $data['name'] . "\" $where.";
        }
        $names[$key] = $row;
        $slugs[$slug] = $row;

        if ($data['display_order'] !== '' && !preg_match('/^-?\d{1,6}$/', $data['display_order'])) {
            $errors[] = "Row $row: display_order must be a whole number.";
        }

        $prepared[] = [
            'name'          => $name,
            'slug'          => $slug,
            'description'   => sanitize($data['description']),
            'display_order' => (int) $data['display_order'],
            'access_code'   => bulkCode($data['access_code'], 'access code', $row, $codeExists, $seenCodes, $errors),
            'status'        => bulkStatus($data['status'], $row, $errors),
        ];
    }

    if ($errors) {
        return [];
    }

    foreach ($prepared as &$category) {
        if ($category['access_code'] === '') {
            $category['access_code'] = bulkUniqueCode(function () {
                return 'CAT-' . strtoupper(bin2hex(random_bytes(4)));
            }, $codeExists, $seenCodes);
        }
    }
    unset($category);

    return $prepared;
}

/**
 * Check nominee rows for an event, matching each to one of its active categories.
 *
 * @return array The rows, ready to save
 */
function prepareBulkNominees(PDO $pdo, $eventId, array $rows, array &$errors) {
    $categoriesStmt = $pdo->prepare("SELECT id, name FROM categories WHERE event_id = ? AND status = 'active'");
    $categoriesStmt->execute([$eventId]);
    $categories = [];
    foreach ($categoriesStmt->fetchAll() as $category) {
        $categories[bulkNameKey($category['name'])] = (int) $category['id'];
    }

    $nomineesStmt = $pdo->prepare("SELECT n.category_id, n.name FROM nominees n
        JOIN categories c ON n.category_id = c.id WHERE c.event_id = ?");
    $nomineesStmt->execute([$eventId]);
    $taken = [];
    foreach ($nomineesStmt->fetchAll() as $nominee) {
        $taken[$nominee['category_id'] . '|' . bulkNameKey($nominee['name'])] = 0;
    }

    $codeExists = $pdo->prepare("SELECT 1 FROM nominees WHERE shortcode = ?");
    $seenCodes = [];
    $prepared = [];

    foreach ($rows as $item) {
        $row = $item['row'];
        $data = $item['data'];
        $name = sanitize($data['name']);
        $categoryId = $categories[bulkNameKey(sanitize($data['category']))] ?? 0;

        if ($data['category'] === '') {
            $errors[] = "Row $row: the category is empty.";
        } elseif (!$categoryId) {
            $errors[] = "Row $row: there is no active category called \"" . $data['category'] . "\" in this event.";
        }

        if ($name === '') {
            $errors[] = "Row $row: the name is empty.";
        } elseif (mb_strlen($name) > 150) {
            $errors[] = "Row $row: the name is longer than 150 characters.";
        } elseif ($categoryId) {
            $key = $categoryId . '|' . bulkNameKey($name);
            if (isset($taken[$key])) {
                $where = $taken[$key] ? 'is also on row ' . $taken[$key] : 'is already in that category';
                $errors[] = "Row $row: the nominee \"" . $data['name'] . "\" $where.";
            }
            $taken[$key] = $row;
        }

        $prepared[] = [
            'category_id' => $categoryId,
            'name'        => $name,
            'slug'        => bulkSlug($name),
            'description' => sanitize($data['description']),
            'shortcode'   => strtoupper(bulkCode($data['shortcode'], 'shortcode', $row, $codeExists, $seenCodes, $errors)),
            'status'      => bulkStatus($data['status'], $row, $errors),
        ];
    }

    if ($errors) {
        return [];
    }

    foreach ($prepared as &$nominee) {
        if ($nominee['shortcode'] === '') {
            $nominee['shortcode'] = bulkUniqueCode(function () {
                return strtoupper(bin2hex(random_bytes(3)));
            }, $codeExists, $seenCodes);
        }
    }
    unset($nominee);

    return $prepared;
}

/**
 * Save checked rows, all together or not at all.
 */
function saveBulkRows(PDO $pdo, $type, $eventId, array $rows) {
    $pdo->beginTransaction();

    try {
        if ($type === 'categories') {
            $insert = $pdo->prepare("INSERT INTO categories (event_id, name, slug, description, display_order, access_code, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($rows as $row) {
                $insert->execute([$eventId, $row['name'], $row['slug'], $row['description'], $row['display_order'], $row['access_code'], $row['status']]);
            }
        } else {
            $insert = $pdo->prepare("INSERT INTO nominees (category_id, name, slug, description, shortcode, status)
                VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($rows as $row) {
                $insert->execute([$row['category_id'], $row['name'], $row['slug'], $row['description'], $row['shortcode'], $row['status']]);
            }
        }

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        throw $e;
    }
}

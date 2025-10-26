<?php
// helpers.php - Central location for reusable utility functions.

// --------------------------------------------------------------------------
// 1. Authorization Functions
// --------------------------------------------------------------------------

/**
 * Checks if the PHP session is active and if the logged-in user has the 'admin' role.
 */
if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // FIX: Check for 'user_type' (as per config.php)
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
    }
}

/**
 * Checks if the PHP session is active and if the logged-in user has the 'teacher' role.
 */
if (!function_exists('is_teacher')) {
    function is_teacher(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // CRITICAL ADDITION: Check for 'user_type' being 'teacher'
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'teacher';
    }
}

/**
 * Checks if the PHP session is active and if the logged-in user has the 'student' role.
 */
if (!function_exists('is_student')) {
    function is_student(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // FIX: Check for 'user_type' (as per config.php)
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'student';
    }
}
// --------------------------------------------------------------------------
// 2. Input Sanitization Functions
// --------------------------------------------------------------------------

/**
 * Basic sanitization for string input.
 */
if (!function_exists('sanitize_input')) {
    function sanitize_input($data): string
    {
        $data = (string)$data; 
        
        $data = trim($data);
        // NOTE: stripslashes is only needed if magic_quotes_gpc is on, 
        // or if data is directly pulled from DB without proper PDO handling.
        $data = stripslashes($data); 
        $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return $data;
    }
}

// --------------------------------------------------------------------------
// 3. Output/Feedback Functions (Flash Messages)
// --------------------------------------------------------------------------

/**
 * Sets a flash message (e.g., success, error, warning) for the next page load.
 */
if (!function_exists('set_flash_message')) {
    function set_flash_message(string $message, string $type = 'success'): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['flash'] = [
            'message' => $message,
            'type' => $type
        ];
    }
}

/**
 * Displays and clears any existing flash message from the session.
 */
if (!function_exists('display_flash_message')) {
    function display_flash_message(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);

            $html = sprintf(
                '<div class="alert alert-%s alert-dismissible fade show" role="alert">
                    %s
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>',
                htmlspecialchars($flash['type']),
                htmlspecialchars($flash['message'])
            );
            return $html;
        }
        return '';
    }
}

// --------------------------------------------------------------------------
// 4. Pagination Functions (NEW SECTION FOR ADMIN LISTS)
// --------------------------------------------------------------------------

/**
 * Calculates pagination details, executes the query, and returns the data slice.
 * @param PDO $pdo PDO connection object.
 * @param string $count_sql SQL query to count total records (must return a single column).
 * @param string $data_sql SQL query to fetch data (ORDER BY is required, but must NOT contain LIMIT or OFFSET).
 * @param array $params Parameters for the SQL queries (e.g., ['type' => 'student']).
 * @param int $items_per_page Number of items per page.
 * @return array Contains total_pages, current_page, and the sliced results.
 */
if (!function_exists('paginate_results')) {
    function paginate_results(PDO $pdo, string $count_sql, string $data_sql, array $params = [], int $items_per_page = 15): array
    {
        $current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?? 1;
        if ($current_page < 1) $current_page = 1;

        // 1. Get total records
        $stmt_count = $pdo->prepare($count_sql);
        $stmt_count->execute($params);
        $total_items = (int)$stmt_count->fetchColumn();

        $total_pages = ceil($total_items / $items_per_page);
        
        // Adjust current_page if it's too high
        if ($current_page > $total_pages && $total_pages > 0) {
            $current_page = $total_pages;
        } elseif ($total_pages === 0) {
            $current_page = 1;
        }

        $offset = ($current_page - 1) * $items_per_page;

        // 2. Get sliced data
        $data_sql .= " LIMIT :limit OFFSET :offset";
        
        $stmt_data = $pdo->prepare($data_sql);
        
        // Bind parameters for the count query
        foreach ($params as $key => &$value) {
            // Bind value using the key from $params
            $stmt_data->bindParam($key, $value);
        }
        
        // Bind LIMIT and OFFSET (must be bound as integers)
        $stmt_data->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
        $stmt_data->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt_data->execute();
        $results = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total_pages' => (int)$total_pages,
            'current_page' => (int)$current_page,
            'results' => $results,
            'total_items' => (int)$total_items
        ];
    }
}

/**
 * Renders the pagination links HTML using Bootstrap classes.
 * @param int $total_pages Total number of pages.
 * @param int $current_page Current page number.
 * @param string $base_url The URL base (e.g., 'admin_users.php').
 * @param array $url_params Extra URL parameters (e.g., search terms, 'view').
 * @return string HTML output for pagination links.
 */
if (!function_exists('render_pagination_links')) {
    function render_pagination_links(int $total_pages, int $current_page, string $base_url, array $url_params = []): string
    {
        if ($total_pages <= 1) {
            return '';
        }

        $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center mt-4">';
        
        // Build query string from extra parameters, excluding the current 'page'
        $query_params = $url_params;
        $query_string_base = http_build_query($query_params);
        
        if (!empty($query_string_base)) {
            $query_string_base = "&" . $query_string_base;
        }

        // --- Previous button ---
        $prev_disabled = ($current_page <= 1) ? 'disabled' : '';
        $prev_page = max(1, $current_page - 1);
        $html .= '<li class="page-item ' . $prev_disabled . '"><a class="page-link" href="' . $base_url . '?page=' . $prev_page . $query_string_base . '">Previous</a></li>';

        // --- Page links (showing 5 pages centered around current page) ---
        $start = max(1, $current_page - 2);
        $end = min($total_pages, $current_page + 2);
        
        // First page link and ellipsis
        if ($start > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?page=1' . $query_string_base . '">1</a></li>';
            if ($start > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        // Main page loop
        for ($i = $start; $i <= $end; $i++) {
            $active = ($i == $current_page) ? 'active' : '';
            $html .= '<li class="page-item ' . $active . '"><a class="page-link" href="' . $base_url . '?page=' . $i . $query_string_base . '">' . $i . '</a></li>';
        }
        
        // Last page link and ellipsis
        if ($end < $total_pages) {
            if ($end < $total_pages - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?page=' . $total_pages . $query_string_base . '">' . $total_pages . '</a></li>';
        }

        // --- Next button ---
        $next_disabled = ($current_page >= $total_pages) ? 'disabled' : '';
        $next_page = min($total_pages, $current_page + 1);
        $html .= '<li class="page-item ' . $next_disabled . '"><a class="page-link" href="' . $base_url . '?page=' . $next_page . $query_string_base . '">Next</a></li>';

        $html .= '</ul></nav>';
        return $html;
    }
}

// --------------------------------------------------------------------------
// 5. Utility Functions (e.g., date formatting, other helpers)
// --------------------------------------------------------------------------
?>
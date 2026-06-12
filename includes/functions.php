<?php
// FILE    : includes/functions.php
// PURPOSE : Reusable helper functions used across admin and student areas.
// USAGE   : require_once __DIR__ . '/../includes/functions.php';
//           (after config/app.php is loaded)

require_once __DIR__ . '/../config/app.php';

// Redirect to a path relative to BASE_URL
// Example: redirect('/admin/dashboard.php');
function redirect(string $path): void
{
    header('Location: ' . BASE_URL . $path);
    exit();
}

// Escape output for safe HTML display (prevents XSS)
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Full name of the currently logged-in user from session
function current_user_full_name(): string
{
    $first = $_SESSION['first_name'] ?? '';
    $last  = $_SESSION['last_name'] ?? '';
    return trim($first . ' ' . $last);
}

// French labels for ticket statuses (for badges and tables)
function ticket_status_label(string $status): string
{
    $labels = [
        'draft'       => 'Brouillon',
        'new'         => 'Nouveau',
        'opened'      => 'Ouvert',
        'in_progress' => 'En cours',
        'completed'   => 'Terminé',
        'rejected'    => 'Rejeté',
    ];

    return $labels[$status] ?? $status;
}

// Bootstrap badge CSS class for each ticket status
function ticket_status_badge(string $status): string
{
    $classes = [
        'draft'       => 'bg-secondary',
        'new'         => 'bg-primary',
        'opened'      => 'bg-info text-dark',
        'in_progress' => 'bg-warning text-dark',
        'completed'   => 'bg-success',
        'rejected'    => 'bg-danger',
    ];

    return $classes[$status] ?? 'bg-secondary';
}

// French labels for ticket priority
function ticket_priority_label(string $priority): string
{
    $labels = [
        'low'    => 'Basse',
        'medium' => 'Moyenne',
        'high'   => 'Haute',
        'urgent' => 'Urgente',
    ];

    return $labels[$priority] ?? $priority;
}

function ticket_priority_badge(string $priority): string
{
    $classes = [
        'low'    => 'bg-light text-dark border',
        'medium' => 'bg-secondary',
        'high'   => 'bg-warning text-dark',
        'urgent' => 'bg-danger',
    ];

    return $classes[$priority] ?? 'bg-secondary';
}

// Format a MySQL datetime for display (French-style short date)
function format_datetime(?string $datetime): string
{
    if (empty($datetime)) {
        return '—';
    }

    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return '—';
    }

    return date('d/m/Y H:i', $timestamp);
}

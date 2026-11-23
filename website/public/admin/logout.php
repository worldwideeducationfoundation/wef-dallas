<?php
require_once __DIR__ . '/includes/bootstrap.php';

// POST + CSRF only (the Log Out button in the top bar is a form). A plain GET
// - an old bookmark, or a link/image on another site - lands back on the
// dashboard still signed in instead of silently ending the session.
requirePostWithCsrf('dashboard.php');

logoutUser();
redirect('login.php');

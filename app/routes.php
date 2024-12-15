<?php

// Home page
$app->get('/', "Watson\Controller\HomeController::indexAction")->bind('home');

// Detailed info about a link
$app->match('/link/{id}', "Watson\Controller\HomeController::linkAction")->bind('link');

// List links with same tag
$app->match('/tag/{id}', "Watson\Controller\HomeController::tagAction")->bind('tag');

// Login form
$app->get('/login', "Watson\Controller\HomeController::loginAction")->bind('login');

$app->get('/feed', "Watson\Controller\HomeController::feedAction")->bind('feed');

// Admin zone
$app->get('/admin', "Watson\Controller\AdminController::indexAction")->bind('admin');

// Add a new link
$app->match('/admin/link/add', "Watson\Controller\AdminController::addLinkAction")->bind('admin_link_add');

// Edit an existing link
$app->match('/admin/link/{id}/edit', "Watson\Controller\AdminController::editLinkAction")->bind('admin_link_edit');

// Remove a link
$app->get('/admin/link/{id}/delete', "Watson\Controller\AdminController::deleteLinkAction")->bind('admin_link_delete');

// Add a user
$app->match('/admin/user/add', "Watson\Controller\AdminController::addUserAction")->bind('admin_user_add');

// Edit an existing user
$app->match('/admin/user/{id}/edit', "Watson\Controller\AdminController::editUserAction")->bind('admin_user_edit');

// Remove a user
$app->get('/admin/user/{id}/delete', "Watson\Controller\AdminController::deleteUserAction")->bind('admin_user_delete');
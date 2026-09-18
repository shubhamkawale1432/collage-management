<?php
require_once __DIR__.'/includes/bootstrap.php';
if (current_user()) { redirect('dashboard.php'); }
$appName = (string)($app['name'] ?? 'Digital College Operating System');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($appName) ?> · Admissions</title>
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/app.css">
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/public-home.css">
</head>
<body class="public-home">
<div class="public-top"><div class="wrap"><span>Admissions open · Academic Session 2026–27</span><span>Campus information & student services</span></div></div>
<nav class="public-nav"><div class="wrap">
    <a class="public-brand" href="<?= e(BASE_URL) ?>/index.php"><span class="public-brand-mark">C</span><span><strong>COLLEGE PORTAL</strong><small>Digital College Operating System</small></span></a>
    <div class="public-links"><a href="#offers">Offers</a><a href="#programs">Programs</a><a href="#why-us">Why Us</a><a class="public-cta" href="<?= e(BASE_URL) ?>/auth/login.php">Student / Staff Login</a></div>
    <a class="mobile-menu" href="<?= e(BASE_URL) ?>/auth/login.php">Login</a>
</div></nav>

<main>
<section class="public-hero"><div class="wrap">
    <div>
        <div class="eyebrow">Welcome to our campus</div>
        <h1>Build your future with <span>purpose.</span></h1>
        <p>Explore programs, discover admission opportunities and take your first step toward a connected, career-focused college experience.</p>
        <div class="hero-actions"><a class="hero-btn primary" href="#offers">Explore Offers →</a><a class="hero-btn outline" href="#admission">Start Admission</a></div>
    </div>
    <aside class="hero-panel"><h3>2026–27 Admissions</h3><p>Plan your application with our digital admission experience.</p>
        <div class="offer-mini"><span class="offer-icon">01</span><span><strong>Applications open</strong><small>Check available programs and eligibility</small></span></div>
        <div class="offer-mini"><span class="offer-icon">02</span><span><strong>Scholarship support</strong><small>Explore eligible financial-aid opportunities</small></span></div>
        <div class="offer-mini"><span class="offer-icon">03</span><span><strong>Digital student portal</strong><small>Track academic services in one place</small></span></div>
    </aside>
</div></section>

<section class="public-section" id="offers"><div class="section-head"><div><div class="section-kicker">Featured opportunities</div><h2>Offers & admission highlights</h2></div><p>Clear information, simple next steps and a modern digital front door for prospective students.</p></div>
<div class="offer-grid">
    <article class="offer-card"><img class="offer-img" loading="lazy" src="https://images.unsplash.com/photo-1523240795612-9a054b0db644?auto=format&fit=crop&w=1000&q=80" alt="Students studying together"><div class="offer-content"><span class="offer-tag">Admissions</span><h3>Start your application</h3><p>Review your course options and continue to the secure college portal.</p><a class="text-link" href="<?= e(BASE_URL) ?>/auth/login.php">Apply / Login →</a></div></article>
    <article class="offer-card"><img class="offer-img" loading="lazy" src="https://images.unsplash.com/photo-1541339907198-e08756dedf3f?auto=format&fit=crop&w=1000&q=80" alt="University campus building"><div class="offer-content"><span class="offer-tag">Campus</span><h3>Explore student life</h3><p>Discover academic support, digital services, activities and career-focused learning.</p><a class="text-link" href="#why-us">Discover more →</a></div></article>
    <article class="offer-card"><img class="offer-img" loading="lazy" src="https://images.unsplash.com/photo-1523050854058-8df90110c9f1?auto=format&fit=crop&w=1000&q=80" alt="Graduates celebrating"><div class="offer-content"><span class="offer-tag">Future ready</span><h3>Career-focused journey</h3><p>Build practical skills with a student experience designed around your next step.</p><a class="text-link" href="#programs">View programs →</a></div></article>
</div></section>

<section class="admission-band" id="admission"><div class="public-section"><div><div class="section-kicker" style="color:#f1d49b">Admissions 2026–27</div><h2>Your next chapter starts here.</h2><p>Ready to begin? Sign in to the college portal to continue your application and access student services.</p></div><a class="admission-btn" href="<?= e(BASE_URL) ?>/auth/login.php">Continue to Admission →</a></div></section>

<section class="public-section" id="programs"><div class="section-head"><div><div class="section-kicker">Student experience</div><h2>Everything in one place</h2></div><p>A unified interface for students, faculty and college operations.</p></div>
<div class="feature-grid" id="why-us">
    <article class="feature"><div class="feature-icon">A</div><h3>Academic Management</h3><p>Results, examinations, timetable and academic information through one portal.</p></article>
    <article class="feature"><div class="feature-icon">S</div><h3>Student Services</h3><p>Applications, documents, profile services and important college updates.</p></article>
    <article class="feature"><div class="feature-icon">C</div><h3>Career Support</h3><p>Placement-focused tools and career information for student growth.</p></article>
    <article class="feature"><div class="feature-icon">D</div><h3>Digital First</h3><p>A responsive experience built for desktop, tablet and mobile users.</p></article>
</div></section>
</main>
<footer class="public-footer"><div class="wrap"><div><strong><?= e($appName) ?></strong><br><small>Digital campus experience · 2026–27</small></div><div><a href="<?= e(BASE_URL) ?>/auth/login.php">Portal Login</a> &nbsp; · &nbsp; <a href="#admission">Admissions</a> &nbsp; · &nbsp; <a href="#offers">Offers</a></div></div></footer>
</body></html>

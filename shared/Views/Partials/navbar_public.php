<nav class="navbar navbar-expand-lg fixed-top marketing-nav">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center text-decoration-none" href="/">
            <img src="/assets/img/workeddy.png" alt="WorkEddy" class="site-logo">
        </a>
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#marketingNavbar" aria-controls="marketingNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <i class="bi bi-list fs-2"></i>
        </button>
        <div class="collapse navbar-collapse" id="marketingNavbar">
            <?php $aboutPages = ['about', 'founder-message', 'why-us', 'contact-us']; ?>
            <ul class="navbar-nav mx-auto mb-3 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="/">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="/#faq">FAQ</a></li>
                <li class="nav-item"><a class="nav-link" href="/#pricing">Pricing</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle<?= in_array(($page ?? ''), $aboutPages, true) ? ' active' : '' ?>" href="#" id="aboutDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        About Us
                    </a>
                    <ul class="dropdown-menu marketing-dropdown" aria-labelledby="aboutDropdown">
                        <li><a class="dropdown-item<?= ($page ?? '') === 'about' ? ' active' : '' ?>" href="/about-us">Our Company</a></li>
                        <li><a class="dropdown-item<?= ($page ?? '') === 'founder-message' ? ' active' : '' ?>" href="/founder-message">Founder's Message</a></li>
                        <li><a class="dropdown-item<?= ($page ?? '') === 'why-us' ? ' active' : '' ?>" href="/why-us">Why Us</a></li>
                        <li><a class="dropdown-item<?= ($page ?? '') === 'contact-us' ? ' active' : '' ?>" href="/contact-us">Contact Us</a></li>
                    </ul>
                </li>
            </ul>
            <div class="d-flex gap-2 flex-column flex-lg-row">
                <a href="/login" class="btn btn-link site-login">Login</a>
                <a href="/register" class="btn btn-primary">Get started</a>
            </div>
        </div>
    </div>
</nav>

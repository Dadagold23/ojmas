<footer class="footer py-4">
    <div class="container">
        <div class="row g-3 align-items-center">
            <div class="col-lg-6">
                <p class="mb-2">&copy; {{ date('Y') }} OJMAS Journal - Version {{ config('journal.version') }}. All rights reserved.</p>
                <p class="mb-1 small">P-ISSN: 3121-7508 | E-ISSN: 3156-5182</p>
                <p class="mb-3 small">License: {{ $journalHighlights['license_version'] ?? 'Creative Commons Attribution 4.0 International License' }}</p>
                <div class="d-flex align-items-center gap-3">
                    <a href="#" class="text-decoration-none text-light" aria-label="Facebook">
                        <i class="bi bi-facebook"></i>
                    </a>
                    <a href="#" class="text-decoration-none text-light" aria-label="X">
                        <i class="bi bi-twitter-x"></i>
                    </a>
                    <a href="#" class="text-decoration-none text-light" aria-label="Instagram">
                        <i class="bi bi-instagram"></i>
                    </a>
                    <a href="#" class="text-decoration-none text-light" aria-label="LinkedIn">
                        <i class="bi bi-linkedin"></i>
                    </a>
                </div>
            </div>
            <div class="col-lg-6">
                <h6 class="text-white fw-bold mb-3 text-lg-end text-start">Quick Links</h6>
                <div class="row row-cols-2 g-1 text-lg-end text-start small">
                    <div class="col"><a href="{{ route('home') }}">Home</a></div>
                    <div class="col"><a href="{{ route('editorial') }}">Editorial</a></div>
                    <div class="col"><a href="{{ route('editorial.board') }}">Editorial Board</a></div>
                    <div class="col"><a href="{{ route('editorial.advisers') }}">Advisers</a></div>
                    <div class="col"><a href="{{ route('scope.policies') }}">Scope & Policies</a></div>
                    <div class="col"><a href="{{ route('call.for.papers') }}">Call for Papers</a></div>
                    <div class="col"><a href="{{ route('archives') }}">Archives</a></div>
                    <div class="col"><a href="{{ route('issues.index') }}">Issues</a></div>
                    <div class="col"><a href="#about">About</a></div>
                    <div class="col"><a href="#contact">Contact</a></div>
                </div>
            </div>
        </div>
    </div>
</footer>

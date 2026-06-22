<style>
.board-hero {
    border: none;
    border-radius: 1.75rem;
    overflow: hidden;
    color: #fff;
    background:
        radial-gradient(circle at top left, rgba(255, 255, 255, .14), transparent 30%),
        linear-gradient(135deg, #0f172a, #0b5ed7 55%, #1d4ed8);
    box-shadow: 0 24px 60px rgba(15, 23, 42, .18);
}

.board-subtitle {
    color: rgba(255, 255, 255, .78);
    max-width: 720px;
}

.profile-card {
    position: relative;
    border: none;
    border-radius: 1.4rem;
    overflow: hidden;
    background: linear-gradient(180deg, #ffffff, #f8fbff);
    box-shadow: 0 22px 45px rgba(15, 23, 42, .08);
    transform-style: preserve-3d;
    transition: transform .18s ease, box-shadow .18s ease;
}

.profile-card::before {
    content: "";
    position: absolute;
    inset: 0;
    background:
        linear-gradient(135deg, rgba(13, 110, 253, .16), transparent 35%),
        radial-gradient(circle at 90% 15%, rgba(245, 158, 11, .2), transparent 22%);
    pointer-events: none;
}

.profile-card:hover,
.profile-card:focus-within {
    box-shadow: 0 28px 55px rgba(15, 23, 42, .14);
}

.profile-portrait {
    height: 230px;
    position: relative;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    width: min(84%, 240px);
    margin: 1.1rem auto 0;
    border-radius: 50% / 40%;
    background:
        linear-gradient(160deg, rgba(15, 23, 42, .9), rgba(37, 99, 235, .82)),
        linear-gradient(45deg, rgba(245, 158, 11, .4), transparent);
    overflow: hidden;
}

.profile-photo {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: contain;
    object-position: center center;
    padding: .85rem;
}

.profile-hover {
    position: absolute;
    inset: 0;
    z-index: 2;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: 1.35rem;
    background: linear-gradient(180deg, rgba(15, 23, 42, .08), rgba(15, 23, 42, .94));
    color: #fff;
    opacity: 0;
    transform: translateY(18px);
    transition: opacity .22s ease, transform .22s ease;
}

.profile-card:hover .profile-hover,
.profile-card:focus-within .profile-hover {
    opacity: 1;
    transform: translateY(0);
}

.profile-hover-name {
    font-size: 1.05rem;
    font-weight: 800;
    line-height: 1.3;
    margin-bottom: .35rem;
}

.profile-hover-role {
    font-size: .9rem;
    font-weight: 700;
    color: rgba(255, 255, 255, .84);
    margin-bottom: .4rem;
}

.profile-hover-meta {
    font-size: .9rem;
    color: rgba(255, 255, 255, .9);
    margin-bottom: 0;
}

.profile-overlay {
    position: absolute;
    inset: 0;
    z-index: 3;
    display: flex;
    flex-direction: column;
    padding: 1.25rem;
    background: rgba(15, 23, 42, .96);
    color: #fff;
    opacity: 0;
    pointer-events: none;
    transition: opacity .22s ease;
}

.profile-card:hover .profile-overlay,
.profile-card:focus-within .profile-overlay {
    opacity: 1;
    pointer-events: auto;
}

.profile-overlay-title {
    font-size: 1rem;
    font-weight: 800;
    margin-bottom: .35rem;
}

.profile-overlay-role {
    font-size: .88rem;
    color: rgba(255, 255, 255, .78);
    margin-bottom: .85rem;
}

.profile-overlay-text {
    font-size: .9rem;
    line-height: 1.55;
    color: rgba(255, 255, 255, .92);
    overflow-y: auto;
    padding-right: .2rem;
}

.profile-portrait::before {
    content: "";
    position: absolute;
    width: 180px;
    height: 180px;
    border-radius: 50%;
    top: 24px;
    background: rgba(255, 255, 255, .08);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .08);
}

.profile-portrait::after {
    content: "";
    position: absolute;
    bottom: 10px;
    width: 70%;
    height: 28px;
    background: rgba(255, 255, 255, .12);
    filter: blur(22px);
}

.profile-initials {
    position: relative;
    z-index: 1;
    width: 110px;
    height: 110px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    margin-bottom: 2.3rem;
    color: #fff;
    font-size: 2rem;
    font-weight: 800;
    letter-spacing: .08em;
    background: linear-gradient(135deg, rgba(245, 158, 11, .95), rgba(244, 114, 182, .85));
    box-shadow: 0 16px 30px rgba(0, 0, 0, .16);
}

.profile-pill {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    padding: .45rem .8rem;
    border-radius: 999px;
    background: rgba(13, 110, 253, .08);
    color: #0b5ed7;
    font-size: .84rem;
    font-weight: 700;
}

.profile-meta {
    color: #64748b;
    font-size: .95rem;
}
</style>

<div class="container py-4">
    <div class="card board-hero mb-5" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="fw-bold mb-3">{{ $title }}</h1>
                    <p class="board-subtitle mb-0">{{ $subtitle }}</p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <i class="bi bi-people-fill" style="font-size: 4.8rem; opacity: .16;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        @foreach($members as $member)
        @php
        $parts = preg_split('/\s+/', trim($member['name']));
        $initials = collect($parts)->take(2)->map(fn ($part) => strtoupper(substr($part, 0, 1)))->implode('');
        $profileText = $member['profile'] ?? null;
        @endphp
        <div class="col-md-6 col-xl-4" data-aos="fade-up">
            <div class="card profile-card h-100 js-profile-card">
                <div class="profile-portrait">
                    @if(!empty($member['image']))
                    <img src="{{ asset($member['image']) }}" alt="{{ $member['name'] }}" class="profile-photo">
                    @endif
                    @if(empty($member['image']))
                    <div class="profile-initials">{{ $initials }}</div>
                    @endif
                    <div class="profile-hover">
                        <div class="profile-hover-name">{{ $member['name'] }}</div>
                        <div class="profile-hover-role">{{ $member['position'] }} | {{ $member['qualification'] }}</div>
                        <p class="profile-hover-meta">{{ $member['profession'] }}</p>
                        <p class="profile-hover-meta">{{ $member['institution'] }}</p>
                    </div>
                </div>
                <div class="card-body p-4 position-relative">
                    <span class="profile-pill mb-3">
                        <i class="bi bi-award-fill"></i>{{ $member['position'] }}
                    </span>
                    <h4 class="fw-bold mb-2">{{ $member['name'] }}</h4>
                    <div class="fw-semibold text-primary mb-2">{{ $member['qualification'] }}</div>
                    <p class="mb-2">{{ $member['profession'] }}</p>
                    <p class="profile-meta mb-0">{{ $member['institution'] }}</p>
                    @if($profileText)
                    <div class="profile-overlay">
                        <div class="profile-overlay-title">{{ $member['name'] }}</div>
                        <div class="profile-overlay-role">{{ $member['position'] }} | {{ $member['qualification'] }}
                        </div>
                        <div class="profile-overlay-text">{!! nl2br(e($profileText)) !!}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>

@push('scripts')
<script>
document.querySelectorAll('.js-profile-card').forEach((card) => {
    card.addEventListener('mousemove', (event) => {
        const rect = card.getBoundingClientRect();
        const x = event.clientX - rect.left;
        const y = event.clientY - rect.top;
        const rotateY = ((x / rect.width) - 0.5) * 10;
        const rotateX = ((y / rect.height) - 0.5) * -10;
        card.style.transform =
            `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-6px)`;
    });

    card.addEventListener('mouseleave', () => {
        card.style.transform = 'perspective(1000px) rotateX(20deg) rotateY(20deg)';
    });
});
</script>
@endpush

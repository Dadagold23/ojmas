<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $issue->title }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 13px; color: #1e293b; line-height: 1.65; margin: 28px; }
        .cover { background: linear-gradient(135deg, #4f46e5, #0f172a); color: #fff; padding: 34px 28px; border-radius: 18px; margin-bottom: 24px; }
        .cover h1 { font-size: 28px; margin: 0 0 10px; }
        .cover p { margin: 4px 0; opacity: .92; }
        .section-title { font-size: 16px; font-weight: 700; color: #0f172a; margin: 20px 0 10px; }
        .article { border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 14px; margin-bottom: 12px; }
        .article-title { font-size: 15px; font-weight: 700; color: #0f172a; margin-bottom: 5px; }
        .meta { font-size: 12px; color: #64748b; margin-bottom: 6px; }
        .footer { margin-top: 24px; font-size: 12px; color: #64748b; text-align: center; }
    </style>
</head>
<body>
    <div class="cover">
        <div>OJMAS</div>
        <h1>{{ $issue->title }}</h1>
        @if(!empty($issue->volume))
            <p>Volume: {{ $issue->volume->title }}</p>
        @endif
        @if(!empty($issue->issue_no))
            <p>Issue Number: {{ $issue->issue_no }}</p>
        @endif
        <p>Published: {{ $issue->published_on ? \Carbon\Carbon::parse($issue->published_on)->format('d M Y') : $issue->created_at?->format('d M Y') }}</p>
        <p>Total Articles: {{ $issue->articles->count() }}</p>
    </div>

    <div class="section-title">Issue Contents</div>

    @forelse($issue->articles ?? [] as $article)
        <div class="article">
            <div class="article-title">{{ $article->title }}</div>
            <div class="meta">Author: {{ $article->submission->author->name ?? 'Unknown Author' }}</div>
            <div>{{ \Illuminate\Support\Str::limit($article->abstract ?? '', 350) }}</div>
        </div>
    @empty
        <div>No articles attached to this issue.</div>
    @endforelse

    <div class="footer">Generated from OJMAS issue archive.</div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $article->title }}</title>
    <style>
        @page{
            margin: 28px 28px 28px 28px;
        }

        html, body{
            margin: 0;
            padding: 0;
        }

        body{
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 13px;
            color: #1e293b;
            line-height: 1.7;
        }

        .title{
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 10px;
            line-height: 1.3;
            page-break-after: avoid;
        }

        .meta{
            font-size: 12px;
            color: #475569;
            margin-bottom: 4px;
        }

        .section-title{
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 22px;
            margin-bottom: 8px;
            border-left: 3px solid #0d6efd;
            padding-left: 10px;
            page-break-after: avoid;
            page-break-inside: avoid;
        }

        .box{
            border: 1px solid #e2e8f0;
            padding: 12px 14px;
            border-radius: 6px;
            margin-bottom: 14px;
            background: #f8fafc;
        }

        .coauthor{
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 10px;
            background: #f8fafc;
            page-break-inside: avoid;
        }

        .full-text{
            text-align: justify;
        }

        /* A trailing margin on the final element pushes a fresh, empty page
           past the page box. Never leave bottom margin on last-child. */
        .box:last-child,
        .coauthor:last-child,
        .full-text:last-child{
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="title">{{ $article->title }}</div>
    <div class="meta"><strong>Author:</strong> {{ $article->author->name ?? 'Unknown Author' }}</div>
    <div class="meta"><strong>Published:</strong> {{ $article->published_on?->format('d M Y') ?? '-' }}</div>
    @if(!empty($article->issue))
        <div class="meta"><strong>Issue:</strong> {{ $article->issue->title }}</div>
    @endif
    @if(!empty($article->issue?->volume))
        <div class="meta"><strong>Volume:</strong> {{ $article->issue->volume->title }}</div>
    @endif

    <div class="section-title">Abstract</div>
    <div class="box">
        {!! nl2br(e($article->abstract ?? '')) !!}
    </div>

    @if(!empty($article->keywords))
        <div class="section-title">Keywords</div>
        <div class="box">{{ $article->keywords }}</div>
    @endif

    @if(!empty($article->full_text))
        <div class="section-title">Full Text</div>
        <div class="full-text">
            {!! nl2br(e(strip_tags($article->full_text))) !!}
        </div>
    @endif

    @if(($article->submission->articleAuthors ?? collect())->count())
        <div class="section-title">Co-Authors</div>
        @foreach($article->submission->articleAuthors as $coAuthor)
            <div class="coauthor">
                <strong>{{ $coAuthor->full_name }}</strong><br>
                {{ $coAuthor->institution ?? '' }}<br>
                {{ $coAuthor->email ?? '' }}<br>
                {{ $coAuthor->country ?? '' }}
            </div>
        @endforeach
    @endif
</body>
</html>

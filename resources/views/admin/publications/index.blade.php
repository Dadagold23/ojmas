@extends('layouts.app')

@section('content')
<style>
    .panel-card { border: none; border-radius: 1.25rem; box-shadow: 0 18px 40px rgba(15,23,42,.08); }
    .pub-hero { border: none; border-radius: 1.5rem;
        background: linear-gradient(135deg, rgba(16,185,129,.92), rgba(15,23,42,.92));
        color:#fff; box-shadow: 0 20px 50px rgba(15,23,42,.16); }
</style>

<div class="container py-4">
    <div class="card pub-hero mb-4" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h2 class="fw-bold mb-2">Published Journal Articles</h2>
                    <p class="mb-0 text-white-50">Upload, manage and track all published journal articles and their PDF files.</p>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                    <a href="{{ route('admin.publications.create') }}" class="btn btn-warning btn-lg fw-semibold">
                        <i class="bi bi-cloud-upload me-2"></i>Publish Article
                    </a>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4">
        {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="card panel-card" data-aos="fade-up">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4">Title</th>
                            <th>Author</th>
                            <th>Issue</th>
                            <th>Published By</th>
                            <th>Date</th>
                            <th>PDF</th>
                            <th class="pe-4 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($publications as $pub)
                        <tr>
                            <td class="px-4">
                                <div class="fw-semibold small">{{ Str::limit($pub->article->title ?? '—', 50) }}</div>
                                <div class="text-muted" style="font-size:.75rem">{{ $pub->article->submission->submission_no ?? '' }}</div>
                            </td>
                            <td class="small">{{ $pub->article->submission->author->name ?? '—' }}</td>
                            <td class="small text-muted">{{ $pub->article->issue->title ?? '—' }}</td>
                            <td class="small text-muted">{{ $pub->publisher->name ?? '—' }}</td>
                            <td class="small text-muted">{{ $pub->published_on?->format('d M Y') ?? '—' }}</td>
                            <td>
                                @if($pub->published_pdf_path)
                                <a href="{{ route('admin.publications.download', $pub) }}"
                                   class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-download me-1"></i>PDF
                                </a>
                                @else
                                <span class="text-muted small">No PDF</span>
                                @endif
                            </td>
                            <td class="pe-4 text-end">
                                <a href="{{ route('admin.publications.show', $pub) }}"
                                   class="btn btn-sm btn-outline-secondary" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('admin.publications.edit', $pub) }}"
                                   class="btn btn-sm btn-outline-primary ms-1" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="{{ route('admin.publications.destroy', $pub) }}"
                                      class="d-inline" onsubmit="return confirm('Remove this publication?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger ms-1" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">No publications yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($publications->hasPages())
        <div class="card-footer bg-white border-0 px-4 pb-4">{{ $publications->links() }}</div>
        @endif
    </div>
</div>
@endsection

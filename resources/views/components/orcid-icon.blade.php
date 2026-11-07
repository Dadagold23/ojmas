@props(['size' => 20, 'title' => 'ORCID iD'])

{{-- Official ORCID iD mark: green (#A6CE39) circle with white "iD". --}}
<svg xmlns="http://www.w3.org/2000/svg" width="{{ $size }}" height="{{ $size }}"
     viewBox="0 0 256 256" role="img" aria-label="{{ $title }}" focusable="false"
     style="flex:0 0 auto">
    <circle cx="128" cy="128" r="128" fill="#A6CE39"/>
    <text x="128" y="176" text-anchor="middle"
          font-family="Arial, Helvetica, sans-serif" font-size="132" font-weight="700"
          fill="#ffffff">iD</text>
</svg>

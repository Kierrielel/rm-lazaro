@extends('layouts.customer')

@section('title', 'Home')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/home.css') }}">
@endpush

@section('content')


@php
    $banners = [
        '1banner.jpg',
        'banner3.jpg',
        '1banner.jpg',
    ];
@endphp

    <div class="banner-images">
        <div class="banner-slider">
        
            @foreach ($banners as $i => $banner)
                <img
                    src="{{ asset('images/' . $banner) }}"
                    alt="banner {{ $i + 1 }}"
                    class="banner-slide {{ $i === 0 ? 'active' : '' }}"
                >
            @endforeach
        
        
            @if (count($banners) > 1)
                <button type="button" class="banner-arrow banner-prev" onclick="showSlide(current - 1)" aria-label="Previous banner">&#10094;</button>
                <button type="button" class="banner-arrow banner-next" onclick="showSlide(current + 1)" aria-label="Next banner">&#10095;</button>
        
                <div class="banner-dots">
                    @foreach ($banners as $i => $banner)
                        <button
                            type="button"
                            class="banner-dot {{ $i === 0 ? 'active' : '' }}"
                            onclick="showSlide({{ $i }})"
                            aria-label="Go to banner {{ $i + 1 }}"
                        ></button>
                    @endforeach
                </div>
            @endif
            
        </div>
    </div>
    
    <section class="products-section">
        <h2>Featured Products</h2>
        <div class="products-grid">
            @foreach ($featured as $product)
                <a href="{{ route('products.show', $product) }}" class="product-card">
                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}">
                    <h4>{{ $product->name }}</h4>
                    <p>&#8369;{{ number_format($product->price, 2) }}</p>
                </a>
            @endforeach
        </div>
    </section>

    <script>
        var slides = document.querySelectorAll('.banner-slide');
        var dots = document.querySelectorAll('.banner-dot');
        var current = 0;
    
        
        function showSlide(number) {
        
            current = (number + slides.length) % slides.length;
        
            for (var i = 0; i < slides.length; i++) {
                slides[i].classList.remove('active');
                dots[i].classList.remove('active');
            }
        
            slides[current].classList.add('active');
            dots[current].classList.add('active');
        }
    
        if (slides.length > 1) {
            setInterval(function () {
                showSlide(current + 1);
            }, 3500);
        }
    </script>
@endsection
@extends('layouts.app')
@section('body-class', 'page-product')

@section('title', \InnoShop\Common\Libraries\MetaInfo::getInstance($product)->getTitle())
@section('description', \InnoShop\Common\Libraries\MetaInfo::getInstance($product)->getDescription())
@section('keywords', \InnoShop\Common\Libraries\MetaInfo::getInstance($product)->getKeywords())

@push('header')
  <script src="{{ asset('vendor/swiper/swiper-bundle.min.js') }}"></script>
  <link rel="stylesheet" href="{{ asset('vendor/swiper/swiper-bundle.min.css') }}">

  <script src="{{ asset('vendor/photoswipe/umd/photoswipe.umd.min.js') }}"></script>
  <script src="{{ asset('vendor/photoswipe/umd/photoswipe-lightbox.umd.min.js') }}"></script>
  <link rel="stylesheet" href="{{ asset('vendor/photoswipe/photoswipe.css') }}">
  
  <script src="{{ asset('vendor/video-js/video.min.js') }}"></script>
  <link href="{{ asset('vendor/video-js/video-js.css') }}" rel="stylesheet">
  
@endpush

@section('content')

  <x-front-breadcrumb type="product" :value="$product"/>

  @hookinsert('product.show.top')

  <div class="container">
    <div class="page-product-top">
      <div class="row">
        <div class="col-12 col-lg-6 product-left-col">
          <div class="product-images">
            @include('products.components._images')
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <div class="product-info">
            <h1 class="product-title">{{ $product->fallbackName() }}</h1>
            @hookupdate('front.product.show.price')
            <div class="product-price">
              <span class="price">{{ $sku['price_format'] }}</span>
              @if($sku['origin_price'])
                <span class="old-price ms-2">{{ $sku['origin_price_format'] }}</span>
              @endif
            </div>
            @endhookupdate

            <div class="stock-wrap">
              @if($sku['quantity'] > 0)
                <div class="in-stock badge">{{ __('front/product.in_stock') }}</div>
              @else
                <div class="out-stock badge d-none">{{ __('front/product.out_stock') }}</div>
              @endif
            </div>

            @hookinsert('product.detail.stock.after')

            <div class="sub-product-title">{{ $product->fallbackName('summary') }}</div>

            @include('products.components._bundle_items')

            <ul class="product-param">
              <li class="sku"><span class="title">{{ __('front/product.sku_code') }}:</span> <span
                  class="value">{{ $sku['code'] }}</span></li>
              <li class="model {{ !($sku['model'] ?? false) ? 'd-none' : '' }}"><span class="title">{{ __('front/product.model') }}:</span>
                <span class="value">{{ $sku['model'] }}</span></li>
              @if ($product->categories->count())
                <li class="category">
                  <span class="title">{{ __('front/product.category') }}:</span>
                  <span class="value">
                @foreach ($product->categories as $category)
                      <a href="{{ $category->url }}"
                         class="text-dark">{{ $category->fallbackName() }}</a>{{ !$loop->last ? ', ' : '' }}
                    @endforeach
              </span>
                </li>
              @endif
              @if($product->brand)
                <li class="brand">
                  <span class="title">{{ __('front/product.brand') }}:</span> <span class="value">
                <a href="{{ $product->brand->url }}"> {{ $product->brand->name }} </a>
              </span>
                </li>
              @endif
              @hookinsert('product.detail.brand.after')
            </ul>

            @include('products.components._variants')
            
            @include('products.components._options')

            @if(!system_setting('disable_online_order'))
              <div class="product-info-bottom">
                <div class="quantity-wrap">
                  <div class="minus"><i class="bi bi-dash-lg"></i></div>
                  <input type="number" class="form-control product-quantity" value="1"
                         data-sku-id="{{ $sku['id'] }}">
                  <div class="plus"><i class="bi bi-plus-lg"></i></div>
                </div>
                <div class="product-info-btns">
                  <button class="btn btn-primary add-cart" data-id="{{ $product->id }}"
                          data-price="{{ $product->masterSku->price }}">
                    {{ __('front/product.add_to_cart') }}
                  </button>
                  <button class="btn buy-now ms-2" data-id="{{ $product->id }}"
                          data-price="{{ $product->masterSku->price }}">
                    {{ __('front/product.buy_now') }}
                  </button>
                  @hookinsert('product.detail.cart.after')
                </div>
              </div>
            @endif

            <div class="add-wishlist mb-3" data-in-wishlist="{{ $product->hasFavorite() }}"
                 data-id="{{ $product->id }}"
                 data-price="{{ $product->masterSku->price }}">
              <i
                class="bi bi-heart{{ $product->hasFavorite() ? '-fill' : '' }}"></i> {{ __('front/product.add_wishlist') }}
            </div>
            @hookinsert('product.detail.after')
          </div>
        </div>
      </div>
    </div>

    <div class="product-description">
      <ul class="nav nav-tabs tabs-plus">
        <li class="nav-item">
          <button class="nav-link active" data-bs-toggle="tab"
                  data-bs-target="#product-description-description"
                  type="button">{{ __('front/product.description') }}</button>
        </li>
        @if($attributes)
          <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#product-description-attribute"
                    type="button">{{ __('front/product.attribute') }}</button>
          </li>
        @endif
        <li class="nav-item">
          <button class="nav-link" data-bs-toggle="tab" data-bs-target="#product-review"
                  type="button">{{ __('front/product.review') }}</button>
        </li>
  <li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#product-description-size-guide"
      type="button">{{ __('Size Guide', [], 'en') ?: 'Size Guide' }}</button>
  </li>
        <li class="nav-item">
          <button class="nav-link correlation" data-bs-toggle="tab"
                  data-bs-target="#product-description-correlation"
                  type="button">{{__('front/product.related_product')}}
          </button>
        </li>
        @hookinsert('product.detail.tab.link.after')
      </ul>
      <div class="tab-content">
        <div class="tab-pane fade show active" id="product-description-description">
          @if($product->fallbackName('selling_point'))
            {!! parsedown($product->fallbackName('selling_point')) !!}
          @endif
          {!! $product->fallbackName('content') !!}
          @hookinsert('product.detail.description.after')
        </div>

        @if($attributes)
          <div class="tab-pane fade" id="product-description-attribute" role="tabpanel">
            <table class="table table-bordered attribute-table">
              @foreach ($attributes as $group)
                <thead class="table-light">
                <tr>
                  <td colspan="2"><strong>{{ $group['attribute_group_name'] }}</strong></td>
                </tr>
                </thead>
                <tbody>
                @foreach ($group['attributes'] as $item)
                  <tr>
                    <td>{{ $item['attribute'] }}</td>
                    <td>{{ $item['attribute_value'] }}</td>
                  </tr>
                @endforeach
                </tbody>
              @endforeach
            </table>
          </div>
        @endif

        <div class="tab-pane fade" id="product-description-size-guide" role="tabpanel">
          @php
            $sizeGuideDir = public_path('images/size-guides');
            $sizeImages = [];
            if (is_dir($sizeGuideDir)) {
              $files = glob($sizeGuideDir . '/*.{jpg,jpeg,png,gif,svg,webp}', GLOB_BRACE);
              if ($files) {
                foreach ($files as $f) {
                  $sizeImages[] = asset('images/size-guides/' . basename($f));
                }
              }
            }
          @endphp

          <div class="container">
            <div class="row">
              <div class="col-12">
                @if(count($sizeImages))
                  {{-- Desktop / md+ slider (multi-column Swiper) --}}
                  <div class="d-none d-md-block">
                    <div class="swiper" id="size-guide-swiper-desktop">
                      <div class="size-guide-swiper-desktop swiper-wrapper">
                        @foreach($sizeImages as $img)
                          <div class="swiper-slide">
                            <div class="size-guide-img">
                              <img src="{{ $img }}" alt="Size guide image" class="img-fluid rounded shadow-sm" />
                            </div>
                          </div>
                        @endforeach
                      </div>
                      <div class="size-guide-pagination" id="size-guide-pagination-desktop"></div>
                      <div class="swiper-button-prev"></div>
                      <div class="swiper-button-next"></div>
                    </div>

                    <script>
                        var sgDesktop = new Swiper('#size-guide-swiper-desktop', {
                        slidesPerView: 2,
                        spaceBetween: 20,
                        pagination: false,
                        navigation: {
                          nextEl: '#size-guide-swiper-desktop .swiper-button-next',
                          prevEl: '#size-guide-swiper-desktop .swiper-button-prev',
                        },
                        breakpoints: {
                          992: { slidesPerView: 3 },
                          1200: { slidesPerView: 3 }
                        },
                        autoplay: {
                          delay: 3000,
                          disableOnInteraction: false,
                        },
                        loop: false,
                      });
                      // build custom pagination for desktop
                      (function buildDesktopPagination() {
                        var container = document.getElementById('size-guide-pagination-desktop');
                        if (!container) return;
                        container.innerHTML = '';
                        for (var i = 0; i < sgDesktop.slides.length; i++) {
                          var btn = document.createElement('button');
                          btn.type = 'button';
                          btn.className = 'size-guide-page' + (i === sgDesktop.activeIndex ? ' active' : '');
                          btn.setAttribute('data-index', i);
                          (function (index) {
                            btn.addEventListener('click', function () { sgDesktop.slideTo(index); });
                          })(i);
                          container.appendChild(btn);
                        }
                        sgDesktop.on('slideChange', function () {
                          var nodes = container.querySelectorAll('.size-guide-page');
                          nodes.forEach(function (n, idx) { n.classList.toggle('active', idx === sgDesktop.activeIndex); });
                        });
                      })();
                    </script>
                  </div>

                  {{-- Mobile swiper (replicates Home slider structure) --}}
                  <div class="d-block d-md-none">
                    <div class="swiper" id="size-guide-swiper-1">
                      <div class="size-guide-swiper swiper-wrapper">
                        @foreach($sizeImages as $img)
                          <div class="swiper-slide">
                            <div class="size-guide-img">
                              <img src="{{ $img }}" alt="Size guide image" class="img-fluid rounded shadow-sm" />
                            </div>
                          </div>
                        @endforeach
                      </div>
                      <div class="size-guide-pagination" id="size-guide-pagination-1"></div>
                      <div class="swiper-button-prev"></div>
                      <div class="swiper-button-next"></div>
                    </div>

                    <script>
                        var sgSwiper = new Swiper('#size-guide-swiper-1', {
                        slidesPerView: 1,
                        spaceBetween: 12,
                        pagination: false,
                        navigation: {
                          nextEl: '#size-guide-swiper-1 .swiper-button-next',
                          prevEl: '#size-guide-swiper-1 .swiper-button-prev',
                        },
                        autoplay: {
                          delay: 3000,
                          disableOnInteraction: false,
                        },
                        loop: false,
                      });
                      // build custom pagination for mobile
                      (function buildMobilePagination() {
                        var container = document.getElementById('size-guide-pagination-1');
                        if (!container) return;
                        container.innerHTML = '';
                        for (var i = 0; i < sgSwiper.slides.length; i++) {
                          var btn = document.createElement('button');
                          btn.type = 'button';
                          btn.className = 'size-guide-page' + (i === sgSwiper.activeIndex ? ' active' : '');
                          btn.setAttribute('data-index', i);
                          (function (index) {
                            btn.addEventListener('click', function () { sgSwiper.slideTo(index); });
                          })(i);
                          container.appendChild(btn);
                        }
                        sgSwiper.on('slideChange', function () {
                          var nodes = container.querySelectorAll('.size-guide-page');
                          nodes.forEach(function (n, idx) { n.classList.toggle('active', idx === sgSwiper.activeIndex); });
                        });
                      })();
                    </script>
                  </div>
                @else
                  <div class="alert alert-info">
                    No size guide images found. Add images to public/images/size-guides (jpg, png, svg).
                  </div>
                @endif
              </div>
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="product-review" role="tabpanel">
          @include('products.components._review_section')
        </div>
        <div class="tab-pane fade" id="product-description-correlation">
          <div class="row gx-3 gx-lg-4">
            @foreach ($related as $relatedItem)
              <div class="col-6 col-md-4 col-lg-3">
                @include('shared.product', ['product'=>$relatedItem])
              </div>
            @endforeach
          </div>
        </div>
        @hookinsert('product.detail.tab.pane.after')
      </div>
    </div>

    @hookinsert('product.show.bottom')

  </div>

@endsection

@push('footer')
  <script>
    $('.quantity-wrap .plus, .quantity-wrap .minus').on('click', function () {
      if ($(this).parent().hasClass('disabled')) {
        return;
      }

      let quantity = parseInt($(this).siblings('input').val());
      if ($(this).hasClass('plus')) {
        $(this).siblings('input').val(quantity + 1);
      } else {
        if (quantity > 1) {
          $(this).siblings('input').val(quantity - 1);
        }
      }
    });

    $('.add-cart, .buy-now').on('click', function () {
      // 验证必需选项是否已选择
      if (typeof validateRequiredOptions === 'function' && !validateRequiredOptions()) {
        // 滚动到第一个错误的选项组
        const $firstError = $('.option-group.has-error').first();
        if ($firstError.length) {
          $('html, body').animate({
            scrollTop: $firstError.offset().top - 100
          }, 500);
        }
        
        if (window.inno && window.inno.alert) {
          window.inno.alert({msg: '{{ __("front/product.please_select_required_options") }}', type: 'warning'});
        } else {
          alert('{{ __("front/product.please_select_required_options") }}');
        }
        return;
      }

      const quantity = $('.product-quantity').val();
      const skuId = $('.product-quantity').data('sku-id');
      const isBuyNow = $(this).hasClass('buy-now');

      // 收集选中的选项
      const productOptions = {};
      
      // 收集下拉选择框的选项
      $('.option-select').each(function() {
        const optionId = $(this).data('option-id');
        const selectedValue = $(this).val();
        if (selectedValue) {
          productOptions[optionId] = [selectedValue];
        }
      });
      
      // 收集单选按钮的选项
      $('.option-radio-item input[type="radio"]:checked').each(function() {
        const optionId = $(this).data('option-id');
        const optionValue = $(this).val();
        productOptions[optionId] = [optionValue];
      });
      
      // 收集多选复选框的选项
      $('.option-checkbox-item input[type="checkbox"]:checked').each(function() {
        const optionId = $(this).data('option-id');
        const optionValue = $(this).val();
        if (!productOptions[optionId]) {
          productOptions[optionId] = [];
        }
        productOptions[optionId].push(optionValue);
      });

      // 准备请求数据
      const requestData = {
        skuId, 
        quantity, 
        isBuyNow,
        options: productOptions
      };
      
      inno.addCart(requestData, this, function (res) {
        if (isBuyNow) {
          
          window.location.href = '{{ front_route('carts.index') }}';
        }
      })
    });
  </script>
@endpush

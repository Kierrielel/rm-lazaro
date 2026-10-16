@extends('layouts.admin')

@section('title', $product ? 'Edit Product' : 'Add Product')

@section('content')
<link rel="stylesheet" href="{{ asset('css/inventory.css') }}">
<link rel="stylesheet" href="{{ asset('css/product-form.css') }}">

<div class="form-page">

    <a href="{{ route('admin.inventory') }}" class="back-link">&larr; Back to inventory</a>

    <div class="form-header">
        <h2>{{ $product ? 'Edit Product' : 'Add Product' }}</h2>
        <p class="subtitle">
            {{ $product ? 'Update the details of this product.' : 'Fill in the details of your new product.' }}
        </p>
    </div>

    <form method="POST"
          action="{{ $product ? route('admin.inventory.update', $product) : route('admin.inventory.store') }}"
          enctype="multipart/form-data"
          class="form-card">
        @csrf
        @if ($product) @method('PUT') @endif

        <div class="form-row">
            <div class="form-group">
                <label for="name">Product name</label>
                <input type="text" name="name" id="name" value="{{ old('name', $product->name ?? '') }}" required>
            </div>

            <div class="form-group">
                <label for="brand">Brand</label>
                <input type="text" name="brand" id="brand" value="{{ old('brand', $product->brand ?? '') }}">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="category_id">Category</label>
                <select name="category_id" id="category_id" required>
                    <option value="">-- Select --</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}"
                            @selected(old('category_id', $product->category_id ?? null) == $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="price">Price (&#8369;)</label>
                <input type="number" step="0.01" name="price" id="price" value="{{ old('price', $product->price ?? '') }}" required>
            </div>

            <div class="form-group">
                <label for="stock_quantity">Stock quantity</label>
                <input type="number" name="stock_quantity" id="stock_quantity" value="{{ old('stock_quantity', $product->stock_quantity ?? '') }}" required>
            </div>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea name="description" id="description" rows="5">{{ old('description', $product->description ?? '') }}</textarea>
        </div>

        @php
            $typeRows = old('option_types');
            if ($typeRows === null) {
                $typeRows = collect($product?->variation_options ?? [])
                    ->map(fn ($values, $name) => ['name' => $name, 'values' => implode(', ', $values)])
                    ->values()->all();
            }

            $variantRows = old('variants');
            if ($variantRows === null) {
                $variantRows = $product
                    ? $product->variants->map(fn ($v) => [
                        'id' => $v->id,
                        'options' => json_encode($v->options),
                        'price' => $v->price,
                        'stock_quantity' => $v->stock_quantity,
                    ])->all()
                    : [];
            }
        @endphp

        <div class="form-group variants-section">
            <h4>Options (optional)</h4>
            <p>Example: Type = For wood, For concrete. Weight = 1 kg, 2 kg, 3 kg.</p>

            <div id="option-types"></div>
            <button type="button" id="add-option-type" class="btn btn-light">+ Add option type</button>
            <button type="button" id="generate-variants" class="btn btn-light">Generate combinations</button>

            <table id="variants-table">
                <thead>
                    <tr><th>Combination</th><th>Price (blank = product price)</th><th>Stock</th><th></th></tr>
                </thead>
                <tbody id="variants-body"></tbody>
            </table>
        </div>

        <script>
        (function () {
            const typesBox = document.getElementById('option-types');
            const body = document.getElementById('variants-body');
            const initialTypes = @json(array_values($typeRows));
            const initialVariants = @json(array_values($variantRows));
            let uid = 1000;

            const keyOf = o => Object.keys(o).sort().map(k => k + '=' + o[k]).join('|');

            function addTypeRow(name = '', values = '') {
                const i = uid++;
                const row = document.createElement('div');
                row.className = 'option-type-row';
                row.innerHTML = `
                    <input name="option_types[${i}][name]" placeholder="Option type, e.g. Weight">
                    <input name="option_types[${i}][values]" placeholder="Values separated by commas, e.g. 1 kg, 2 kg, 3 kg">
                    <button type="button" class="remove-option-type">Remove</button>`;
                row.querySelector('[name$="[name]"]').value = name;
                row.querySelector('[name$="[values]"]').value = values;
                typesBox.appendChild(row);
            }

            function addVariantRow(opts, data = {}) {
                const i = uid++;
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="variant-label"></td>
                    <td><input type="number" step="0.01" min="0" name="variants[${i}][price]" placeholder="Same as product"></td>
                    <td><input type="number" step="any" min="0" name="variants[${i}][stock_quantity]" required></td>
                    <td>
                        <input type="hidden" name="variants[${i}][id]">
                        <input type="hidden" name="variants[${i}][options]">
                        <button type="button" class="remove-variant">Remove</button>
                    </td>`;
                tr.querySelector('.variant-label').textContent = Object.values(opts).join(' / ');
                tr.querySelector('[name$="[options]"]').value = JSON.stringify(opts);
                tr.querySelector('[name$="[id]"]').value = data.id ?? '';
                tr.querySelector('[name$="[price]"]').value = data.price ?? '';
                tr.querySelector('[name$="[stock_quantity]"]').value = data.stock ?? 0;
                body.appendChild(tr);
            }

            function readTypes() {
                const types = [];
                typesBox.querySelectorAll('.option-type-row').forEach(row => {
                    const name = row.querySelector('[name$="[name]"]').value.trim();
                    const values = [...new Set(
                        row.querySelector('[name$="[values]"]').value.split(',').map(v => v.trim()).filter(Boolean)
                    )];
                    if (name && values.length) types.push({ name, values });
                });
                return types;
            }

            function readExisting() {
                const map = {};
                body.querySelectorAll('tr').forEach(tr => {
                    const opts = JSON.parse(tr.querySelector('[name$="[options]"]').value);
                    map[keyOf(opts)] = {
                        id: tr.querySelector('[name$="[id]"]').value,
                        price: tr.querySelector('[name$="[price]"]').value,
                        stock: tr.querySelector('[name$="[stock_quantity]"]').value,
                    };
                });
                return map;
            }

            function combos(types) {
                if (!types.length) return [];
                return types.reduce(
                    (acc, t) => acc.flatMap(c => t.values.map(v => ({ ...c, [t.name]: v }))),
                    [{}]
                );
            }

            document.getElementById('add-option-type').addEventListener('click', () => addTypeRow());

            document.getElementById('generate-variants').addEventListener('click', () => {
                const all = combos(readTypes());
                const existing = readExisting();
                const newKeys = new Set(all.map(keyOf));
                const dropped = Object.keys(existing).filter(k => !newKeys.has(k)).length;

                if (dropped && !confirm(dropped + ' existing combination(s) will be removed when you save, along with their cart lines. Continue?')) {
                    return;
                }

                body.innerHTML = '';
                all.forEach(c => addVariantRow(c, existing[keyOf(c)] || {}));
            });

            typesBox.addEventListener('click', e => {
                if (e.target.classList.contains('remove-option-type')) e.target.closest('.option-type-row').remove();
            });
            body.addEventListener('click', e => {
                if (e.target.classList.contains('remove-variant')) e.target.closest('tr').remove();
            });

            typesBox.closest('form').addEventListener('submit', e => {
                if (readTypes().length && !body.querySelector('tr')) {
                    e.preventDefault();
                    alert('Click "Generate combinations" before saving so each option gets a price and stock.');
                }
            });

            initialTypes.forEach(t => addTypeRow(t.name, t.values));
            initialVariants.forEach(v => addVariantRow(JSON.parse(v.options), {
                id: v.id, price: v.price, stock: v.stock_quantity,
            }));
        })();
        </script>

        <div class="form-group">
            <label for="image">Product image</label>
            <div class="image-upload">
                @if ($product && $product->image_path)
                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="current-image">
                @endif
                <input type="file" name="image" id="image" accept="image/*">
            </div>
        </div>


        <div class="form-buttons">
            <a href="{{ route('admin.inventory') }}" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-primary">{{ $product ? 'Save Changes' : 'Add Product' }}</button>
        </div>
    </form>

</div>
@endsection
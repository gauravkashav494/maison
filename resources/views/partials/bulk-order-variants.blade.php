{{--
    Size / colour pickers for a picked Bulk order line, shown only when the product
    actually offers a choice. Each template passes its own $selectClass.
--}}
<span class="mt-1.5 flex flex-wrap gap-2" x-show="row.product.sizes.length > 1 || row.product.colors.length > 1">
    <select x-show="row.product.sizes.length > 1" x-model="row.size" aria-label="Size" class="{{ $selectClass }}">
        <template x-for="s in row.product.sizes" :key="s">
            <option :value="s" x-text="s" :selected="s === row.size"></option>
        </template>
    </select>
    <select x-show="row.product.colors.length > 1" x-model="row.color" aria-label="Colour" class="{{ $selectClass }}">
        <template x-for="c in row.product.colors" :key="c.name">
            <option :value="c.name" x-text="c.name" :selected="c.name === row.color"></option>
        </template>
    </select>
</span>

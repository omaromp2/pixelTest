<script setup lang="ts">
import { Form, Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import CreditController from '@/actions/App/Http/Controllers/CreditController';
import GenerationController from '@/actions/App/Http/Controllers/GenerationController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatUsd } from '@/lib/utils';
import { create } from '@/routes/generations';

type TokenType =
    | 'input_text_tokens'
    | 'input_image_tokens'
    | 'output_tokens'
    | 'reasoning_tokens';

type Pricing = {
    rates: Record<TokenType, string>;
    tokens: {
        characters_per_text_token: number;
        text_token_variance: number;
        per_reference_image: number;
        output: Record<string, Record<string, number>>;
        reasoning: Record<string, { min: number; max: number }>;
    };
};

const props = defineProps<{
    pricing: Pricing;
    markup: number;
    sizes: string[];
    qualities: string[];
    balance: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Generate', href: create() }],
    },
});

const form = useForm({
    prompt: '',
    size: '1024x1024',
    quality: 'medium',
    reference_images: '',
});

const referenceImages = computed(() =>
    form.reference_images
        .split('\n')
        .map((url) => url.trim())
        .filter(Boolean),
);

/** Mirrors PixelforgeCost::estimate() on the server: the most this generation can cost, in nanodollars. */
const estimatedCost = computed(() => {
    const { rates, tokens } = props.pricing;
    const characters = [...form.prompt].length;
    const textTokens = Math.ceil(
        Math.ceil(characters / tokens.characters_per_text_token) *
            (1 + tokens.text_token_variance),
    );

    const usage: Record<TokenType, number> = {
        input_text_tokens: textTokens,
        input_image_tokens:
            referenceImages.value.length * tokens.per_reference_image,
        output_tokens: tokens.output[form.quality][form.size],
        reasoning_tokens: tokens.reasoning[form.quality].max,
    };

    // A rate is dollars per 1M tokens, so one token costs rate × 1,000 nanodollars.
    const providerCost = (Object.keys(rates) as TokenType[]).reduce(
        (total, type) => total + usage[type] * Number(rates[type]) * 1000,
        0,
    );

    return Math.ceil(Math.floor(providerCost) * props.markup);
});

const canAfford = computed(() => props.balance >= estimatedCost.value);

const referenceImagesError = computed(
    () =>
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('reference_images'),
        )?.[1],
);

function submit() {
    form.transform((data) => ({
        ...data,
        reference_images: referenceImages.value,
    })).submit(GenerationController.store(), {
        onSuccess: () => form.reset('prompt'),
    });
}
</script>

<template>
    <Head title="Generate" />

    <div class="p-4">
        <section class="flex flex-col gap-6 rounded-xl border p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <Heading
                    title="Generate an image"
                    description="Describe what you want; you pay Pixelforge's cost plus 25%."
                />

                <Form
                    v-bind="CreditController.store.form()"
                    :options="{ preserveScroll: true }"
                    class="flex items-center gap-3"
                    v-slot="{ processing }"
                >
                    <span class="text-sm">
                        Balance:
                        <strong>{{ formatUsd(balance) }}</strong>
                    </span>
                    <Button
                        type="submit"
                        variant="outline"
                        size="sm"
                        :disabled="processing"
                    >
                        Buy $10 credits
                    </Button>
                </Form>
            </div>

            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="prompt">Prompt</Label>
                    <textarea
                        id="prompt"
                        v-model="form.prompt"
                        rows="3"
                        maxlength="4000"
                        required
                        placeholder="A lighthouse on a cliff at dawn, oil painting"
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                    />
                    <InputError :message="form.errors.prompt" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label>Size</Label>
                        <Select v-model="form.size">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="size in sizes"
                                    :key="size"
                                    :value="size"
                                >
                                    {{ size }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.size" />
                    </div>

                    <div class="grid gap-2">
                        <Label>Quality</Label>
                        <Select v-model="form.quality">
                            <SelectTrigger class="w-full capitalize">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="quality in qualities"
                                    :key="quality"
                                    :value="quality"
                                    class="capitalize"
                                >
                                    {{ quality }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.quality" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="reference_images">
                        Reference image URLs (optional, one per line, up to 4)
                    </Label>
                    <textarea
                        id="reference_images"
                        v-model="form.reference_images"
                        rows="2"
                        placeholder="https://example.com/style.png"
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                    />
                    <InputError :message="referenceImagesError" />
                </div>

                <div class="flex flex-wrap items-center justify-between gap-4">
                    <p class="text-sm">
                        Estimated cost: at most
                        <strong>{{ formatUsd(estimatedCost) }}</strong>
                        <span class="text-muted-foreground">
                            (you're charged the actual cost when it finishes,
                            nothing if it fails)
                        </span>
                    </p>
                    <Button
                        type="submit"
                        :disabled="form.processing || !canAfford"
                    >
                        {{ canAfford ? 'Generate' : 'Not enough credits' }}
                    </Button>
                </div>
            </form>
        </section>
    </div>
</template>

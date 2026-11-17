<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { computed, watchEffect } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatUsd } from '@/lib/utils';
import { create, index } from '@/routes/generations';

type Generation = {
    id: string;
    status: 'queued' | 'processing' | 'succeeded' | 'failed';
    prompt: string;
    size: string;
    quality: string;
    image_url: string | null;
    image_url_expires_at: string | null;
    error_message: string | null;
    estimated_cost: number;
    charged_cost: number | null;
    created_at: string;
};

const props = defineProps<{ generations: Generation[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'History', href: index() }],
    },
});

const hasPendingGenerations = computed(() =>
    props.generations.some(
        (generation) =>
            generation.status === 'queued' ||
            generation.status === 'processing',
    ),
);

const { start, stop } = usePoll(
    3000,
    { only: ['generations'] },
    { autoStart: false },
);

watchEffect(() => (hasPendingGenerations.value ? start() : stop()));

function isImageAvailable(generation: Generation): boolean {
    return (
        generation.image_url !== null &&
        new Date(generation.image_url_expires_at ?? 0) > new Date()
    );
}

function statusVariant(status: Generation['status']) {
    return {
        queued: 'secondary',
        processing: 'secondary',
        succeeded: 'default',
        failed: 'destructive',
    }[status] as 'secondary' | 'default' | 'destructive';
}
</script>

<template>
    <Head title="History" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between gap-4">
            <Heading
                title="History"
                description="Your generations, newest first."
            />
            <Button as-child>
                <Link :href="create()">New image</Link>
            </Button>
        </div>

        <p
            v-if="generations.length === 0"
            class="text-sm text-muted-foreground"
        >
            No generations yet.
        </p>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <article
                v-for="generation in generations"
                :key="generation.id"
                class="flex flex-col overflow-hidden rounded-xl border"
            >
                <div
                    class="flex aspect-square items-center justify-center bg-muted text-sm text-muted-foreground"
                >
                    <img
                        v-if="isImageAvailable(generation)"
                        :src="generation.image_url!"
                        :alt="generation.prompt"
                        class="size-full object-contain"
                    />
                    <span v-else-if="generation.status === 'succeeded'">
                        Image link expired
                    </span>
                    <span v-else-if="generation.status === 'failed'">
                        {{ generation.error_message }}
                    </span>
                    <span v-else class="animate-pulse">Generating…</span>
                </div>

                <div class="flex flex-col gap-2 p-4 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <Badge
                            :variant="statusVariant(generation.status)"
                            class="capitalize"
                        >
                            {{ generation.status }}
                        </Badge>
                        <span class="text-muted-foreground">
                            {{ generation.size }} ·
                            {{ generation.quality }}
                        </span>
                    </div>
                    <p class="line-clamp-2">{{ generation.prompt }}</p>
                    <dl class="grid grid-cols-2 text-muted-foreground">
                        <dt>Estimated</dt>
                        <dd class="text-right">
                            {{ formatUsd(generation.estimated_cost) }}
                        </dd>
                        <dt>Actual</dt>
                        <dd class="text-right text-foreground">
                            {{
                                generation.charged_cost === null
                                    ? '—'
                                    : formatUsd(generation.charged_cost)
                            }}
                        </dd>
                    </dl>
                </div>
            </article>
        </div>
    </div>
</template>

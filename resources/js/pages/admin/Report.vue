<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { formatUsd } from '@/lib/utils';
import { report } from '@/routes/admin';

type UserRow = {
    name: string;
    email: string;
    generations: number;
    charged: number;
    provider_cost: number;
};

type Day = {
    date: string;
    charged: number;
    provider_cost: number;
    pixelforge_reported_cost: number | null;
    users: UserRow[];
};

defineProps<{ days: Day[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Admin report', href: report() }],
    },
});
</script>

<template>
    <Head title="Admin report" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            title="Credit spend, provider cost and profit"
            description="Last 30 days, by the UTC day each generation completed. Pixelforge column comes from GET /v1/usage."
        />

        <p v-if="days.length === 0" class="text-sm text-muted-foreground">
            No billed generations in the last 30 days.
        </p>

        <table v-else class="w-full text-sm">
            <thead class="text-left text-muted-foreground">
                <tr class="border-b">
                    <th class="py-2">Day / user</th>
                    <th class="py-2 text-right">Generations</th>
                    <th class="py-2 text-right">Charged</th>
                    <th class="py-2 text-right">Provider cost</th>
                    <th class="py-2 text-right">Profit</th>
                    <th class="py-2 text-right">Pixelforge reported</th>
                </tr>
            </thead>
            <tbody v-for="day in days" :key="day.date">
                <tr class="border-b bg-muted/50 font-medium">
                    <td class="py-2">{{ day.date }}</td>
                    <td />
                    <td class="py-2 text-right">
                        {{ formatUsd(day.charged) }}
                    </td>
                    <td class="py-2 text-right">
                        {{ formatUsd(day.provider_cost) }}
                    </td>
                    <td class="py-2 text-right">
                        {{ formatUsd(day.charged - day.provider_cost) }}
                    </td>
                    <td
                        class="py-2 text-right"
                        :class="{
                            'text-destructive':
                                day.pixelforge_reported_cost !== null &&
                                day.pixelforge_reported_cost !==
                                    day.provider_cost,
                        }"
                    >
                        {{
                            day.pixelforge_reported_cost === null
                                ? 'unavailable'
                                : formatUsd(day.pixelforge_reported_cost)
                        }}
                    </td>
                </tr>
                <tr
                    v-for="user in day.users"
                    :key="user.email"
                    class="border-b"
                >
                    <td class="py-2 pl-4">
                        {{ user.name }}
                        <span class="text-muted-foreground">
                            {{ user.email }}
                        </span>
                    </td>
                    <td class="py-2 text-right">{{ user.generations }}</td>
                    <td class="py-2 text-right">
                        {{ formatUsd(user.charged) }}
                    </td>
                    <td class="py-2 text-right">
                        {{ formatUsd(user.provider_cost) }}
                    </td>
                    <td class="py-2 text-right">
                        {{ formatUsd(user.charged - user.provider_cost) }}
                    </td>
                    <td />
                </tr>
            </tbody>
        </table>
    </div>
</template>

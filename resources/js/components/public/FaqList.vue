<script setup lang="ts">
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import type { PreguntaFrecuente } from './types';

defineProps<{ preguntas: PreguntaFrecuente[] }>();
</script>

<template>
    <Accordion
        type="single"
        collapsible
        class="divide-y divide-border/70 overflow-hidden rounded-3xl border border-border/70 bg-card"
    >
        <AccordionItem
            v-for="(item, indice) in preguntas"
            :key="indice"
            :value="`faq-${indice}`"
            class="border-b-0 px-5 transition-colors data-[state=open]:bg-brand-green-soft/50 sm:px-7"
        >
            <AccordionTrigger
                class="py-5 text-left font-sans text-[15px] font-semibold hover:no-underline sm:text-base [&>svg]:size-5 [&>svg]:text-brand-green-strong"
            >
                {{ item.pregunta }}
            </AccordionTrigger>
            <AccordionContent
                class="pb-5 text-[15px] leading-relaxed whitespace-pre-line text-muted-foreground"
            >
                {{ item.respuesta }}
            </AccordionContent>
        </AccordionItem>
    </Accordion>
</template>

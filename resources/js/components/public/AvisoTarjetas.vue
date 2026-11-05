<script setup lang="ts">
import { CreditCard } from 'lucide-vue-next';

/**
 * Aviso informativo obligatorio: aún no se aceptan tarjetas (sin campos de captura).
 * El texto es exacto por requerimiento del negocio; no modificar.
 */
</script>

<template>
    <div
        class="flex items-start gap-4 rounded-2xl border border-dashed border-brand-teal/60 bg-brand-teal-soft/60 p-4 sm:p-5"
        role="note"
    >
        <span
            class="flex size-10 shrink-0 items-center justify-center rounded-full bg-background text-brand-teal-strong shadow-sm"
        >
            <CreditCard class="size-5" aria-hidden="true" />
        </span>
        <div>
            <p class="text-sm font-semibold text-foreground sm:text-[15px]">
                Próximamente aceptaremos tarjetas de crédito y débito.
            </p>
            <p class="mt-1 text-sm leading-relaxed text-muted-foreground">
                Estamos trabajando para incorporar pagos seguros con tarjeta.
                Por ahora puedes realizar tus compras mediante transferencia
                bancaria o efectivo.
            </p>
        </div>
    </div>
</template>

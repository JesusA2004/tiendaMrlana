<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

declare global {
    interface Window {
        __WTT_BUDDY_ANCHOR__?: { x: number; y: number };
    }
}

const props = withDefaults(
    defineProps<{
        srcDesktop?: string;
        srcMobile?: string;
        widthDesktop?: number;
        widthMobile?: number;
        edge?: number;
        anchorXDesktop?: number;
        anchorYDesktop?: number;
        anchorXMobile?: number;
        anchorYMobile?: number;
    }>(),
    {
        srcDesktop: '/img/footer/bg-whabutton.png',
        srcMobile: '/img/footer/bg-whaticket-mobile.png',
        widthDesktop: 190,
        widthMobile: 92,
        edge: 18,
        anchorXDesktop: 82,
        anchorYDesktop: 60,
        anchorXMobile: 55,
        anchorYMobile: 68,
    },
);

const imgRef = ref<HTMLImageElement | null>(null);
const anchorRef = ref<HTMLDivElement | null>(null);
const isMob = ref(false);

function isMobileViewport() {
    return window.matchMedia('(max-width: 1023px)').matches;
}

const src = computed(() => (isMob.value ? props.srcMobile : props.srcDesktop));
const width = computed(() =>
    isMob.value ? props.widthMobile : props.widthDesktop,
);

const style = ref<Record<string, string>>({
    position: 'fixed',
    zIndex: '9998',
    pointerEvents: 'none',
    bottom: `${props.edge}px`,
    right: `${props.edge}px`,
    left: 'auto',
    width: `${props.widthDesktop}px`,
});

const anchorStyle = computed<Record<string, string>>(() => {
    const x = isMob.value ? props.anchorXMobile : props.anchorXDesktop;
    const y = isMob.value ? props.anchorYMobile : props.anchorYDesktop;

    return {
        left: `${x}%`,
        top: `${y}%`,
        transform: 'translate(-50%, -50%)',
    };
});

function computeAnchorFromRealAnchor() {
    const el = anchorRef.value;

    if (!el) {
        return;
    }

    const rect = el.getBoundingClientRect();
    const next = {
        x: rect.left + rect.width / 2,
        y: rect.top + rect.height / 2,
    };

    window.__WTT_BUDDY_ANCHOR__ = next;
    window.dispatchEvent(
        new CustomEvent('wtt-buddy-anchor-updated', { detail: next }),
    );
}

async function update() {
    isMob.value = isMobileViewport();

    style.value = {
        position: 'fixed',
        zIndex: '9998',
        pointerEvents: 'none',
        bottom: `${props.edge}px`,
        right: `${props.edge}px`,
        left: 'auto',
        width: `${width.value}px`,
    };

    await nextTick();
    computeAnchorFromRealAnchor();
}

const onResize = () => {
    update();
};

const onScroll = () => {
    computeAnchorFromRealAnchor();
};

onMounted(() => {
    window.addEventListener('resize', onResize, { passive: true });
    window.addEventListener('scroll', onScroll, { passive: true });
    update();
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
    window.removeEventListener('scroll', onScroll);
});
</script>

<template>
    <div :style="style" aria-hidden="true">
        <div class="relative">
            <img
                ref="imgRef"
                :src="src"
                class="h-auto w-full select-none"
                loading="lazy"
                draggable="false"
                @load="computeAnchorFromRealAnchor"
            />

            <div
                ref="anchorRef"
                class="absolute h-3 w-3 rounded-full"
                :style="anchorStyle"
            />
        </div>
    </div>
</template>

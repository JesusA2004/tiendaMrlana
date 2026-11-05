import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Button } from "./Button.vue"

export const buttonVariants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-lg text-sm font-semibold transition-[background-color,background-position,color,border-color,box-shadow,transform,scale] duration-200 ease-out active:scale-[0.98] disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:ring-[3px] focus-visible:ring-brand-green/50 focus-visible:ring-offset-1 focus-visible:ring-offset-background aria-invalid:ring-destructive/20 aria-invalid:border-destructive",
  {
    variants: {
      variant: {
        default:
          "shine bg-brand-gradient bg-[length:150%_100%] bg-left text-brand-ink shadow-[0_6px_18px_-8px_rgb(31_77_58/0.45)] hover:bg-right hover:-translate-y-px hover:shadow-[0_12px_26px_-10px_rgb(31_77_58/0.55)]",
        brand:
          "shine bg-primary text-primary-foreground shadow-[0_6px_18px_-8px_rgb(31_77_58/0.45)] hover:bg-[#82d34b] hover:-translate-y-px hover:shadow-[0_12px_26px_-10px_rgb(31_77_58/0.55)]",
        deep:
          "shine bg-brand-deep text-white shadow-[0_6px_18px_-8px_rgb(20_51_43/0.6)] hover:bg-brand-deep-2 hover:-translate-y-px",
        destructive:
          "bg-destructive text-white shadow-sm hover:bg-destructive/90 hover:-translate-y-px focus-visible:ring-destructive/30",
        outline:
          "border border-border bg-card text-foreground shadow-xs hover:border-brand-green hover:bg-brand-green-soft hover:text-brand-deep",
        secondary:
          "bg-secondary text-secondary-foreground hover:bg-surface-strong",
        ghost:
          "hover:bg-brand-green-soft hover:text-brand-deep",
        link: "text-brand-green-strong underline-offset-4 hover:underline",
      },
      size: {
        "default": "h-9 px-4 py-2 has-[>svg]:px-3",
        "sm": "h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5",
        "lg": "h-11 rounded-xl px-6 text-base has-[>svg]:px-4",
        "icon": "size-9",
        "icon-sm": "size-8",
        "icon-lg": "size-10",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  },
)
export type ButtonVariants = VariantProps<typeof buttonVariants>

import * as React from 'react';
import { Slot } from '@radix-ui/react-slot';
import { cva } from 'class-variance-authority';

import { cn } from '@/lib/utils';

const buttonVariants = cva(
	'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-lg text-sm font-semibold no-underline transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0',
	{
		variants: {
			variant: {
				default:
					'border-0 bg-primary text-primary-foreground shadow-sm hover:bg-primary-hover hover:text-primary-foreground',
				destructive:
					'border-0 bg-destructive text-destructive-foreground shadow-sm hover:opacity-90',
				outline:
					'border border-border bg-card text-heading shadow-sm hover:border-primary hover:bg-primary-softer hover:text-primary',
				secondary:
					'border-0 bg-secondary text-secondary-foreground hover:bg-primary-soft hover:text-primary',
				ghost: 'border-0 bg-transparent text-heading hover:bg-primary-softer hover:text-primary',
				link: 'border-0 bg-transparent text-primary underline-offset-4 hover:underline',
			},
			size: {
				default: 'h-10 px-4',
				sm: 'h-8 rounded-md px-3 text-xs',
				lg: 'h-11 px-6',
				icon: 'h-10 w-10',
			},
		},
		defaultVariants: {
			variant: 'default',
			size: 'default',
		},
	}
);

const Button = React.forwardRef(
	( { className, variant, size, asChild = false, ...props }, ref ) => {
		const Comp = asChild ? Slot : 'button';
		return (
			<Comp
				className={ cn(
					buttonVariants( { variant, size, className } )
				) }
				ref={ ref }
				{ ...props }
			/>
		);
	}
);
Button.displayName = 'Button';

export { Button, buttonVariants };

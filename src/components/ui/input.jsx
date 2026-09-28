import * as React from 'react';

import { cn } from '@/lib/utils';

const Input = React.forwardRef( ( { className, type, ...props }, ref ) => {
	return (
		<input
			type={ type }
			className={ cn(
				'flex min-h-[45px] w-full border border-border bg-white rounded-md px-3 py-1 text-base transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus:border-primary disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
				className
			) }
			ref={ ref }
			{ ...props }
		/>
	);
} );
Input.displayName = 'Input';

export { Input };

import { classNames } from '../utils/helpers';

const Container = ( {
	children,
	className,
	as: Element = 'div',
	...props
} ) => {
	return (
		<Element
			className={ classNames(
				'max-w-container w-full bg-white p-8 flex flex-col rounded-xl shadow-md',
				className
			) }
			{ ...props }
		>
			{ children }
		</Element>
	);
};

export default Container;

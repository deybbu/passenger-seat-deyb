import React, { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';

function readProps(root) {
    const script = root.querySelector('script[data-props]');

    if (!script) {
        throw new Error(`Missing React island props for "${root.dataset.reactRoot}".`);
    }

    return JSON.parse(script.textContent);
}

function mount(selector, Component, getProps = readProps) {
    document.querySelectorAll(selector).forEach((element) => {
        createRoot(element).render(<Component {...getProps(element)} />);
    });
}

function OrderTypePicker({ type, onChange, theme = 'brand' }) {
    const red = theme === 'red';
    const activeClass = red ? 'border-red-600 bg-red-50' : 'border-brand-600 bg-brand-50';
    const inactiveClass = red ? 'border-slate-200' : 'border-gray-200';
    const options = [
        { value: 'dine_in', label: 'Dine-in' },
        { value: 'take_out', label: 'Take-out' },
    ];

    return (
        <div className={`grid ${red ? 'gap-3 sm:grid-cols-2' : 'grid-cols-2 gap-2'}`}>
            {options.map((option) => (
                <label
                    key={option.value}
                    className={`${red
                        ? 'flex cursor-pointer gap-3 rounded-xl border p-4'
                        : 'flex cursor-pointer items-center gap-2 rounded-lg border-2 p-3 text-sm font-semibold'} ${type === option.value ? activeClass : inactiveClass}`}
                >
                    <input
                        type="radio"
                        name="type"
                        value={option.value}
                        checked={type === option.value}
                        onChange={() => onChange(option.value)}
                        className={red ? 'mt-1 border-slate-300 text-red-700 focus:ring-red-600' : 'accent-brand-600'}
                        required
                    />
                    {red ? (
                        <span>
                            <span className="block font-bold">{option.label}</span>
                            <span className="mt-1 block text-sm text-slate-500">
                                {option.value === 'dine_in'
                                    ? 'Enjoy your meal at the restaurant.'
                                    : 'Pack your order for takeaway.'}
                            </span>
                        </span>
                    ) : option.label}
                </label>
            ))}
        </div>
    );
}

function CheckoutForm({ type: initialType, tableNumber, tableError, specialInstructions, total }) {
    const [type, setType] = useState(initialType);

    return (
        <div className="space-y-6">
            <fieldset>
                <legend className="text-sm font-bold text-slate-900">Order type</legend>
                <div className="mt-3">
                    <OrderTypePicker type={type} onChange={setType} theme="red" />
                </div>
            </fieldset>
            <div hidden={type !== 'dine_in'}>
                <label htmlFor="table_number" className="block text-sm font-bold">
                    Table number <span className="text-red-700">*</span>
                </label>
                <input
                    id="table_number"
                    name="table_number"
                    defaultValue={tableNumber}
                    required={type === 'dine_in'}
                    maxLength="20"
                    pattern="[A-Za-z0-9 -]+"
                    className="mt-2 w-full rounded-xl border-slate-300 focus:border-red-600 focus:ring-red-600"
                    placeholder="e.g. 12 or A-03"
                />
                {tableError && <p className="mt-1 text-sm text-red-700">{tableError}</p>}
            </div>
            <div>
                <label htmlFor="special_instructions" className="block text-sm font-bold">
                    Special instructions <span className="font-normal text-slate-500">(optional)</span>
                </label>
                <textarea
                    id="special_instructions"
                    name="special_instructions"
                    rows="4"
                    maxLength="1000"
                    defaultValue={specialInstructions}
                    className="mt-2 w-full rounded-xl border-slate-300 focus:border-red-600 focus:ring-red-600"
                    placeholder="Add a request for our team."
                />
            </div>
            <div className="rounded-xl bg-amber-50 p-4 text-sm leading-6 text-amber-900">
                <strong>Payment note:</strong> Online payment is not enabled. After placing your order, proceed to the counter to pay and confirm your transaction.
            </div>
            <button
                type="submit"
                className="w-full rounded-xl bg-red-700 px-5 py-3.5 font-bold text-white hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
            >
                Place order · ₱{Number(total).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </button>
        </div>
    );
}

function CartCheckoutForm({
    type: initialType,
    tableNumber,
    customerName,
    customerPhone,
    specialInstructions,
    hasUnavailable,
    total,
}) {
    const [type, setType] = useState(initialType);

    return (
        <>
            <h2 className="font-display text-2xl font-bold uppercase">Check in</h2>

            <fieldset className="mt-3">
                <legend className="mb-2 text-sm font-semibold">How will you enjoy your order?</legend>
                <OrderTypePicker type={type} onChange={setType} />
            </fieldset>

            <div className="mt-4" hidden={type !== 'dine_in'}>
                <label htmlFor="table_number" className="mb-1 block text-sm font-semibold">Table number</label>
                <input
                    id="table_number"
                    name="table_number"
                    type="text"
                    maxLength="10"
                    required={type === 'dine_in'}
                    defaultValue={tableNumber}
                    className="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-600 focus:ring-2 focus:ring-brand-100"
                />
            </div>

            <div className="mt-4">
                <label htmlFor="customer_name" className="mb-1 block text-sm font-semibold">Your name</label>
                <input
                    id="customer_name"
                    name="customer_name"
                    type="text"
                    maxLength="60"
                    required
                    autoComplete="name"
                    defaultValue={customerName}
                    className="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-600 focus:ring-2 focus:ring-brand-100"
                />
            </div>

            <div className="mt-4">
                <label htmlFor="customer_phone" className="mb-1 block text-sm font-semibold">
                    Mobile number <span className="font-normal text-gray-500">{type === 'take_out' ? '(required for take-out)' : '(optional)'}</span>
                </label>
                <input
                    id="customer_phone"
                    name="customer_phone"
                    type="tel"
                    maxLength="20"
                    autoComplete="tel"
                    required={type === 'take_out'}
                    defaultValue={customerPhone}
                    className="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-600 focus:ring-2 focus:ring-brand-100"
                />
            </div>

            <div className="mt-4">
                <label htmlFor="special_instructions" className="mb-1 block text-sm font-semibold">Special instructions</label>
                <textarea
                    id="special_instructions"
                    name="special_instructions"
                    rows="3"
                    maxLength="500"
                    placeholder="Allergies, no onions, extra spicy..."
                    defaultValue={specialInstructions}
                    className="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-600 focus:ring-2 focus:ring-brand-100"
                />
            </div>

            {hasUnavailable && (
                <p className="mt-4 rounded-lg bg-red-50 p-3 text-sm font-semibold text-red-800">
                    Remove the sold-out items to place your order.
                </p>
            )}

            <button
                type="submit"
                disabled={hasUnavailable}
                className="mt-5 w-full rounded-full bg-brand-600 px-6 py-3 text-base font-bold text-white hover:bg-brand-700 focus:ring-4 focus:ring-brand-100 disabled:cursor-not-allowed disabled:opacity-50"
            >
                Place order · ₱{Number(total).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </button>
            <p className="mt-2 text-center text-xs text-gray-500">You will pay at the counter.</p>
        </>
    );
}

function WalkInOrderForm({ categories, uncategorizedItems, type: initialType, fields }) {
    const [type, setType] = useState(initialType);
    const allItems = [...categories.flatMap((category) => category.items), ...uncategorizedItems];
    const [quantities, setQuantities] = useState(() =>
        Object.fromEntries(allItems.map((item) => [item.id, Number(item.initial) || 0])),
    );
    const count = Object.values(quantities).reduce((sum, quantity) => sum + (Number.parseInt(quantity, 10) || 0), 0);
    const total = allItems.reduce(
        (sum, item) => sum + item.price * (Number.parseInt(quantities[item.id], 10) || 0),
        0,
    );

    const updateQuantity = (id, value) => {
        setQuantities((current) => ({ ...current, [id]: value }));
    };

    const bump = (id, amount) => {
        setQuantities((current) => ({
            ...current,
            [id]: Math.min(20, Math.max(0, (Number.parseInt(current[id], 10) || 0) + amount)),
        }));
    };

    const renderItem = (item) => (
        <div key={item.id} className={`flex items-center justify-between gap-3 px-4 py-3 ${item.orderable ? '' : 'opacity-50'}`}>
            <div>
                <p className="font-semibold">{item.name}</p>
                <p className="text-sm text-gray-500">
                    ₱{item.price.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                    {!item.orderable && ' · Sold out'}
                </p>
            </div>
            {item.orderable && (
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => bump(item.id, -1)}
                        className="grid h-9 w-9 place-items-center rounded-full border border-gray-300 hover:bg-gray-50"
                        aria-label={`Decrease ${item.name}`}
                    >
                        −
                    </button>
                    <input
                        type="number"
                        name={`items[${item.id}]`}
                        min="0"
                        max="20"
                        value={quantities[item.id]}
                        onChange={(event) => updateQuantity(item.id, event.target.value)}
                        className="w-14 rounded-lg border border-gray-300 px-2 py-1.5 text-center"
                        aria-label={`Quantity of ${item.name}`}
                    />
                    <button
                        type="button"
                        onClick={() => bump(item.id, 1)}
                        className="grid h-9 w-9 place-items-center rounded-full border border-gray-300 hover:bg-gray-50"
                        aria-label={`Increase ${item.name}`}
                    >
                        +
                    </button>
                </div>
            )}
        </div>
    );

    return (
        <>
            <div className="space-y-6 lg:col-span-2">
                {allItems.length === 0 ? (
                    <p className="pass p-6 text-center text-sm text-gray-500">
                        No active menu items are available to add to an order.
                    </p>
                ) : (
                    <>
                        {categories.map((category) => (
                            <section key={category.name}>
                                <h2 className="mb-2 border-l-8 border-brand-600 pl-3 font-display text-2xl font-bold uppercase">
                                    {category.name}
                                </h2>
                                <div className="pass divide-y divide-gray-200">{category.items.map(renderItem)}</div>
                            </section>
                        ))}
                        {uncategorizedItems.length > 0 && (
                            <section>
                                <h2 className="mb-2 border-l-8 border-brand-600 pl-3 font-display text-2xl font-bold uppercase">
                                    Uncategorized
                                </h2>
                                <div className="pass divide-y divide-gray-200">{uncategorizedItems.map(renderItem)}</div>
                            </section>
                        )}
                    </>
                )}
            </div>
            <aside className="lg:col-span-1">
                <div className="pass sticky top-4 p-4">
                    <h2 className="font-display text-2xl font-bold uppercase">Order details</h2>
                    <div className="mt-3">
                        <OrderTypePicker type={type} onChange={setType} />
                    </div>

                    <div className="mt-3" hidden={type !== 'dine_in'}>
                        <label htmlFor="table_number" className="mb-1 block text-sm font-semibold">Table number</label>
                        <input
                            id="table_number"
                            name="table_number"
                            type="text"
                            maxLength="10"
                            defaultValue={fields.tableNumber}
                            required={type === 'dine_in'}
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-600 focus:ring-2 focus:ring-brand-100"
                        />
                    </div>

                    <label htmlFor="customer_name" className="mb-1 mt-3 block text-sm font-semibold">
                        Customer name <span className="font-normal text-gray-500">(optional)</span>
                    </label>
                    <input
                        id="customer_name"
                        name="customer_name"
                        type="text"
                        maxLength="60"
                        defaultValue={fields.customerName}
                        className="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-600 focus:ring-2 focus:ring-brand-100"
                    />

                    <label htmlFor="customer_phone" className="mb-1 mt-3 block text-sm font-semibold">
                        Mobile number <span className="font-normal text-gray-500">(optional)</span>
                    </label>
                    <input
                        id="customer_phone"
                        name="customer_phone"
                        type="tel"
                        maxLength="20"
                        defaultValue={fields.customerPhone}
                        className="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-600 focus:ring-2 focus:ring-brand-100"
                    />

                    <label htmlFor="special_instructions" className="mb-1 mt-3 block text-sm font-semibold">
                        Special instructions
                    </label>
                    <textarea
                        id="special_instructions"
                        name="special_instructions"
                        rows="3"
                        maxLength="500"
                        defaultValue={fields.specialInstructions}
                        className="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-600 focus:ring-2 focus:ring-brand-100"
                    />

                    <div className="pass-divider -mx-4 my-4" />
                    <div className="flex items-center justify-between">
                        <span className="text-sm text-gray-600">{count} item(s)</span>
                        <span className="font-display text-3xl font-bold text-brand-700">
                            ₱{total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </span>
                    </div>
                    <button
                        type="submit"
                        className="mt-4 w-full rounded-full bg-brand-600 px-6 py-3 font-bold text-white hover:bg-brand-700 focus:ring-4 focus:ring-brand-100"
                    >
                        Create order
                    </button>
                    <p className="mt-2 text-center text-xs text-gray-500">The final total is recalculated by the server.</p>
                </div>
            </aside>
        </>
    );
}

function OrderBoard({ initialHtml, url }) {
    const [html, setHtml] = useState(initialHtml);
    const [error, setError] = useState('');

    useEffect(() => {
        let active = true;

        const refresh = async () => {
            try {
                const response = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error(`Order board refresh failed (${response.status}).`);
                }

                const nextHtml = await response.text();
                if (active) {
                    setHtml(nextHtml);
                    setError('');
                }
            } catch (cause) {
                if (active) {
                    const message = cause instanceof Error ? cause.message : 'Unknown refresh error.';
                    setError(`Unable to refresh the order board. ${message}`);
                    console.error('Order board refresh failed.', cause);
                }
            }
        };

        const timer = window.setInterval(refresh, 15000);
        return () => {
            active = false;
            window.clearInterval(timer);
        };
    }, [url]);

    return (
        <>
            {error && <p role="alert" className="mb-3 rounded-lg bg-red-50 p-3 text-sm text-red-800">{error}</p>}
            <div dangerouslySetInnerHTML={{ __html: html }} />
        </>
    );
}

mount('[data-react-root="checkout"]', CheckoutForm);
mount('[data-react-root="cart-checkout"]', CartCheckoutForm);
mount('[data-react-root="walk-in-order"]', WalkInOrderForm);
mount('[data-react-root="order-board"]', OrderBoard, (element) => ({
    initialHtml: element.innerHTML,
    url: window.location.href,
}));

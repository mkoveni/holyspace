/** Shared Vuetify validation rules, so every form words its errors the same way. */
export declare function required(value: unknown): true | "This field is required.";
export declare function email(value: string): true | "Enter a valid email address.";
export declare function minLength(length: number): (value: string) => string | true;
export declare function maxLength(length: number): (value: string) => string | true;

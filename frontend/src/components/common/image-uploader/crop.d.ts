export interface ImageSize {
    width: number;
    height: number;
}
export interface CropSettings {
    zoom: number;
    x: number;
    y: number;
}
export declare const imagePresets: {
    readonly person: {
        readonly width: 250;
        readonly height: 250;
    };
    readonly family: {
        readonly width: 1200;
        readonly height: 800;
    };
    readonly event: {
        readonly width: 1280;
        readonly height: 720;
    };
    readonly community: {
        readonly width: 1280;
        readonly height: 720;
    };
    readonly form: {
        readonly width: 1200;
        readonly height: 300;
    };
};
export declare function validateSize(size: ImageSize): void;
/** The source rectangle always stays inside the original image and fills the output. */
export declare function cropRectangle(source: ImageSize, target: ImageSize, crop: CropSettings): {
    x: number;
    y: number;
    width: number;
    height: number;
};

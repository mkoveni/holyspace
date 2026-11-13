export interface EmailTemplate {
    id: string;
    key: string;
    name: string;
    subject: string;
    html: string;
    variables: string[];
    status: 'DRAFT' | 'PUBLISHED' | 'ARCHIVED';
    version: number;
    publishedVersion: number | null;
    updatedAt: string;
}
export interface TemplateDraft {
    key: string;
    name: string;
    subject: string;
    html: string;
    variables: string[];
}
export interface RenderedEmail {
    subject: string;
    html: string;
}
export interface TemplateRevision extends RenderedEmail {
    version: number;
    variables: string[];
    publishedAt: string;
}
export declare const templatesApi: {
    list(search?: string, offset?: number): Promise<{
        templates: EmailTemplate[];
        total: number;
    }>;
    get(id: string): Promise<EmailTemplate>;
    create(draft: TemplateDraft): Promise<{
        id: string;
    }>;
    update(id: string, draft: TemplateDraft, expectedVersion: number): Promise<{
        id: string;
    }>;
    change(id: string, action: "publish" | "archive", expectedVersion: number): Promise<void>;
    preview(draft: TemplateDraft, values: Record<string, string>): Promise<RenderedEmail>;
    revisions(id: string, offset?: number): Promise<TemplateRevision[]>;
};

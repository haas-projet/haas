// Généré par scripts/generate-api-types.php ; ne pas modifier à la main.
// Formes JSON uniquement : validation, formats, bornes et droits restent côté serveur.

export type OPENAPI_AccountRole = identity_AccountRole;

export type OPENAPI_AccountStatus = identity_AccountStatus;

export type OPENAPI_ApiError = { readonly "error": { readonly "code": string; readonly "message": string; readonly "fields": { [key: string]: unknown; }; }; readonly "request_id": string; };

export type OPENAPI_Handle = identity_Handle;

export type OPENAPI_IdempotencyKey = string;

export type OPENAPI_PaginatedResult = { readonly "data": ReadonlyArray<{ [key: string]: unknown; }>; readonly "meta": OPENAPI_PaginationMeta; };

export type OPENAPI_PaginationMeta = { readonly "current_page": number; readonly "per_page": number; readonly "last_page": number; readonly "total": number; readonly "from": number | null; readonly "to": number | null; };

export type OPENAPI_TechnologyId = identity_TechnologyId;

export type account_mail_Email = string;

export type account_mail_ResetPasswordInput = { readonly "email": account_mail_Email; readonly "token": string; readonly "password": string; readonly "password_confirmation": string; };

export type administration_Account = { readonly "id": string; readonly "handle": string; readonly "role": "member" | "moderator" | "admin"; readonly "status": "active" | "suspended"; readonly "lock_version": number; };

export type capsules_lab_CapsuleDraft = { readonly "id": string; readonly "slug": string; readonly "visibility": "visible" | "hidden"; readonly "source": { readonly "kind": "help_request" | "editorial"; readonly "help_request_id"?: string; readonly "editorial_origin"?: string; [key: string]: unknown; }; readonly "version": capsules_lab_CapsuleVersionDraft; [key: string]: unknown; };

export type capsules_lab_CapsuleDraftInput = { readonly "slug": string; readonly "source": { readonly "kind": "help_request" | "editorial"; readonly "help_request_id"?: string; readonly "editorial_origin"?: string; }; readonly "version": capsules_lab_CapsuleVersionDraftInput; };

export type capsules_lab_CapsuleVersionDraft = { readonly "id": string; readonly "capsule_id": string; readonly "version_label": string; readonly "state": "draft" | "in_review" | "changes_requested" | "published" | "withdrawn"; readonly "body": string; readonly "limits": string; readonly "lock_version": number; readonly "technologies": ReadonlyArray<{ readonly "id"?: string; readonly "slug"?: string; readonly "version_label"?: string; [key: string]: unknown; }>; [key: string]: unknown; };

export type capsules_lab_CapsuleVersionDraftInput = { readonly "version_label": string; readonly "body": string; readonly "limits"?: string; readonly "technologies"?: ReadonlyArray<{ readonly "technology_id": string; readonly "version_label"?: string; }>; };

export type capsules_lab_CapsuleVersionDraftUpdate = { readonly "lock_version": number; readonly "body"?: string; readonly "limits"?: string; readonly "technologies"?: ReadonlyArray<{ readonly "technology_id": string; readonly "version_label"?: string; }>; };

export type capsules_lab_CapsuleVersionPublishInput = { readonly "lock_version": number; };

export type capsules_lab_CapsuleVersionPublished = { readonly "id": string; readonly "capsule_id": string; readonly "version_label": string; readonly "state": "published"; readonly "body": string; readonly "limits": string; readonly "content_digest": string; readonly "lock_version": number; readonly "published_at": string; readonly "technologies": ReadonlyArray<{ readonly "id"?: string; readonly "slug"?: string; readonly "version_label"?: string; [key: string]: unknown; }>; [key: string]: unknown; };

export type capsules_lab_CapsuleVersionReview = { readonly "id": string; readonly "version_id": string; readonly "reviewed_lock_version": number; readonly "decision": "request_changes"; readonly "note": string; readonly "created_at"?: string; [key: string]: unknown; };

export type capsules_lab_CapsuleVersionReviewInput = { readonly "lock_version": number; readonly "note": string; };

export type capsules_lab_CapsuleVersionSubmitReviewInput = { readonly "lock_version": number; };

export type community_Comment = { readonly "id": string; readonly "request_id": string; readonly "author": { readonly "id": string; readonly "handle": string; }; readonly "body": string; readonly "body_html": string; readonly "lock_version": number; readonly "created_at": string | null; readonly "edited_at": string | null; };

export type community_CommentCreateInput = { readonly "body": string; };

export type community_CommentDetail = { readonly "data": community_Comment; };

export type community_CommentPage = { readonly "data": ReadonlyArray<community_Comment>; readonly "meta": OPENAPI_PaginationMeta; };

export type community_CommentRevision = { readonly "id": string; readonly "comment_version": number; readonly "action": "created" | "updated" | "before_edit"; readonly "body": string; readonly "body_html": string; readonly "occurred_at": string; };

export type community_CommentRevisionPage = { readonly "data": ReadonlyArray<community_CommentRevision>; readonly "meta": OPENAPI_PaginationMeta; };

export type community_CommentUpdateInput = { readonly "body": string; readonly "lock_version": number; };

export type community_CreateHelpRequestInput = (community_HelpRequestDraftInput | community_HelpRequestPublicationInput | community_HelpRequestQuestionInput);

export type community_HelpIntent = "unblock" | "review_solution" | "reproduce_behavior" | "ask_question";

export type community_HelpRequest = { readonly "help_intent": "unblock" | "review_solution" | "reproduce_behavior" | "ask_question"; readonly "title": string; readonly "goal": string | null; readonly "expected": string | null; readonly "observed": string | null; readonly "attempts": string | null; readonly "environment": string | null; readonly "code": string | null; readonly "code_language": string | null; readonly "primary_language": string; readonly "reproduction_url": string | null; readonly "technologies": ReadonlyArray<{ readonly "id": string; readonly "slug": string; readonly "name": string; readonly "version_label": string | null; }>; readonly "id": string; readonly "author": { readonly "id": string; readonly "handle": string; }; readonly "state": "draft" | "open" | "in_progress" | "resolved" | "archived"; readonly "lock_version": number; readonly "created_at": string; readonly "updated_at": string; };

export type community_HelpRequestCreated = { readonly "data": community_HelpRequest; };

export type community_HelpRequestDetail = { readonly "data": community_HelpRequest; };

export type community_HelpRequestDraftInput = (unknown) & { readonly "mode": "draft"; readonly "help_intent": "unblock" | "review_solution" | "reproduce_behavior" | "ask_question"; readonly "title": string; readonly "goal"?: string | null; readonly "expected"?: string | null; readonly "observed"?: string | null; readonly "attempts"?: string | null; readonly "environment"?: string | null; readonly "code"?: string | null; readonly "code_language"?: string | null; readonly "primary_language"?: string; readonly "reproduction_url"?: string | null; readonly "technologies"?: ReadonlyArray<community_RequestTechnologyInput>; };

export type community_HelpRequestPage = { readonly "data": ReadonlyArray<community_HelpRequest>; readonly "meta": OPENAPI_PaginationMeta; };

export type community_HelpRequestPublicationInput = (unknown) & { readonly "mode": "publish"; readonly "help_intent": "unblock" | "review_solution" | "reproduce_behavior"; readonly "title": string; readonly "goal": string; readonly "expected": string; readonly "observed": string; readonly "attempts": string; readonly "environment": string; readonly "code"?: string | null; readonly "code_language"?: string | null; readonly "primary_language"?: string; readonly "reproduction_url"?: string | null; readonly "technologies": ReadonlyArray<community_RequestTechnologyInput>; };

export type community_HelpRequestPublishInput = { readonly "lock_version": number; };

export type community_HelpRequestQuestionInput = (unknown) & { readonly "mode": "publish"; readonly "help_intent": "ask_question"; readonly "title": string; readonly "goal": string; readonly "expected"?: string | null; readonly "observed": string; readonly "attempts"?: string | null; readonly "environment"?: string | null; readonly "code"?: string | null; readonly "code_language"?: string | null; readonly "primary_language"?: string; readonly "reproduction_url"?: string | null; readonly "technologies": ReadonlyArray<community_RequestTechnologyInput>; };

export type community_HelpRequestRevision = { readonly "id": string; readonly "request_version": number; readonly "action": "updated" | "published"; readonly "changed_fields": ReadonlyArray<"help_intent" | "title" | "goal" | "expected" | "observed" | "attempts" | "environment" | "code" | "code_language" | "primary_language" | "reproduction_url" | "technologies" | "state">; readonly "edit_note": string | null; readonly "occurred_at": string; };

export type community_HelpRequestRevisionPage = { readonly "data": ReadonlyArray<community_HelpRequestRevision>; readonly "meta": OPENAPI_PaginationMeta; };

export type community_HelpRequestUpdateInput = (unknown | unknown | unknown | unknown | unknown | unknown | unknown | unknown | unknown | unknown | unknown | unknown) & { readonly "lock_version": number; readonly "help_intent"?: "unblock" | "review_solution" | "reproduce_behavior" | "ask_question"; readonly "title"?: string; readonly "goal"?: string | null; readonly "expected"?: string | null; readonly "observed"?: string | null; readonly "attempts"?: string | null; readonly "environment"?: string | null; readonly "code"?: string | null; readonly "code_language"?: string | null; readonly "primary_language"?: string; readonly "reproduction_url"?: string | null; readonly "technologies"?: ReadonlyArray<community_RequestTechnologyInput>; readonly "edit_note"?: string | null; };

export type community_RequestTechnologyInput = { readonly "id": string; readonly "version_label"?: string | null; };

export type current_account_Me = { readonly "id": string; readonly "handle": identity_Handle; readonly "email": string; readonly "email_verified": boolean; readonly "role": identity_AccountRole; readonly "status": "active"; readonly "is_demo": boolean; readonly "can": { readonly "manage_account_mail": boolean; readonly "update_profile": boolean; readonly "participate": boolean; readonly "moderate": boolean; readonly "administer": boolean; }; };

export type identity_AccountRole = "member" | "moderator" | "admin";

export type identity_AccountStatus = "active" | "suspended";

export type identity_Handle = string;

export type identity_RegisterMemberInput = { readonly "handle": identity_Handle; readonly "email": string; readonly "password": string; readonly "password_confirmation": string; readonly "terms_accepted": true; readonly "terms_version": string; };

export type identity_RegisteredMember = { readonly "id": string; readonly "handle": identity_Handle; readonly "email_verified": false; };

export type identity_SessionMember = { readonly "id": string; readonly "handle": identity_Handle; readonly "email_verified": boolean; };

export type identity_TechnologyId = string;

export type moderation_Category = "secret_exposed" | "abuse" | "uncertain_rights" | "misleading_solution" | "other";

export type moderation_ModerationReport = (moderation_ReportFields & { readonly "category": moderation_Category; readonly "detail": string; readonly "reporter_id": string | null; readonly "context": { readonly "bio": string; readonly "country": string | null; readonly "github_url": string | null; readonly "hidden": boolean; readonly "lock_version": number; } | null; [key: string]: unknown; });

export type moderation_Receipt = (moderation_ReportFields);

export type moderation_ReportFields = { readonly "id": string; readonly "resource_type": "profile"; readonly "resource_id": string; readonly "status": "new" | "in_review" | "resolved" | "dismissed"; readonly "lock_version": number; readonly "created_at": string; [key: string]: unknown; };

export type notifications_Notification = { readonly "id": string; readonly "kind": "profile.moderated" | "comment.created" | "capsule.review.changes_requested"; readonly "message": string; readonly "target_path": string; readonly "read_at": string | null; readonly "created_at": string; };

export type profiles_ProfileFields = { readonly "id": string; readonly "handle": identity_Handle; readonly "avatar_initials": string; readonly "bio": string; readonly "country": string | null; readonly "primary_language": string; readonly "github_url": string | null; readonly "technologies": ReadonlyArray<profiles_Technology>; readonly "is_demo": boolean; readonly "contributions": null; [key: string]: unknown; };

export type profiles_ProfilePatch = { readonly "lock_version": number; readonly "bio"?: string | null; readonly "country"?: string | null; readonly "primary_language"?: string; readonly "github_url"?: string | null; readonly "technology_ids"?: ReadonlyArray<string>; };

export type profiles_PublicProfile = (profiles_ProfileFields);

export type profiles_Technology = { readonly "id": string; readonly "slug": string; readonly "name": string; };

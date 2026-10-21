-- Ejecutar manualmente después de 04_roles_menu.sql. Solo habilita Admisión.
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
SET NOCOUNT ON;
SET XACT_ABORT ON;
DECLARE @rol int = (SELECT TOP (1) [id] FROM [dbo].[TB_ACCESO_ROL] WHERE [titulo] = N'Admisión' ORDER BY [id]);
DECLARE @menu int = (SELECT TOP (1) [id] FROM [dbo].[TB_ACCESO_MENU] WHERE [link] = N'/oferta-flexible/admision' ORDER BY [id]);
IF @rol IS NULL OR @menu IS NULL
    THROW 51004, N'Ejecute primero 04_roles_menu.sql: falta el rol o el menú de Admisión.', 1;
DECLARE @ahora datetime2(0) = CONVERT(datetime2(0), SYSDATETIMEOFFSET() AT TIME ZONE N'SA Pacific Standard Time');
BEGIN TRANSACTION;
UPDATE [dbo].[TB_ACCESO_MENU] SET [status] = 1, [deleted_by] = NULL, [updated_at] = @ahora WHERE [id] = @menu;
IF NOT EXISTS (SELECT 1 FROM [dbo].[TB_ACCESO_MENU_X_ROL] WITH (UPDLOCK, HOLDLOCK) WHERE [menu_principal_id] = @menu AND [rol_id] = @rol)
    INSERT INTO [dbo].[TB_ACCESO_MENU_X_ROL] ([menu_principal_id], [rol_id], [status], [created_at], [updated_at]) VALUES (@menu, @rol, 1, @ahora, @ahora);
ELSE
    UPDATE [dbo].[TB_ACCESO_MENU_X_ROL] SET [status] = 1, [deleted_by] = NULL, [updated_at] = @ahora WHERE [menu_principal_id] = @menu AND [rol_id] = @rol;
DECLARE @padre int = (SELECT [padre_id] FROM [dbo].[TB_ACCESO_MENU] WHERE [id] = @menu);
IF @padre IS NOT NULL
BEGIN
    UPDATE [dbo].[TB_ACCESO_MENU] SET [status] = 1, [deleted_by] = NULL, [updated_at] = @ahora WHERE [id] = @padre;
    IF NOT EXISTS (SELECT 1 FROM [dbo].[TB_ACCESO_MENU_X_ROL] WITH (UPDLOCK, HOLDLOCK) WHERE [menu_principal_id] = @padre AND [rol_id] = @rol)
        INSERT INTO [dbo].[TB_ACCESO_MENU_X_ROL] ([menu_principal_id], [rol_id], [status], [created_at], [updated_at]) VALUES (@padre, @rol, 1, @ahora, @ahora);
    ELSE
        UPDATE [dbo].[TB_ACCESO_MENU_X_ROL] SET [status] = 1, [deleted_by] = NULL, [updated_at] = @ahora WHERE [menu_principal_id] = @padre AND [rol_id] = @rol;
END;
COMMIT TRANSACTION;

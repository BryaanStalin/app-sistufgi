-- Solo pacientes ficticios. No actualiza registros existentes.
SET ANSI_NULLS ON;
SET ANSI_PADDING ON;
SET ANSI_WARNINGS ON;
SET ARITHABORT ON;
SET CONCAT_NULL_YIELDS_NULL ON;
SET QUOTED_IDENTIFIER ON;
SET NUMERIC_ROUNDABORT OFF;
SET NOCOUNT ON;
SET XACT_ABORT ON;
BEGIN TRANSACTION;
INSERT INTO [dbo].[TB_PACIENTES_MOCK] ([tipo_doc], [dni], [nombres], [apellidos], [fecha_nacimiento], [telefono], [sexo])
SELECT [p].[tipo_doc], [p].[dni], [p].[nombres], [p].[apellidos], CONVERT(date, [p].[nacimiento], 112), N'900000000', [p].[sexo]
FROM (VALUES
 (N'CNV', N'OF-CNV-001', N'LUCÍA', N'NÚÑEZ PRUEBA', N'20240115', N'F'),
 (N'CNV', N'OF-CNV-002', N'JOSÉ', N'PEÑA PRUEBA', N'20250310', N'M'),
 (N'DNI', N'99000001', N'MARÍA', N'MUÑOZ PRUEBA', N'20180612', N'F'),
 (N'DNI', N'99000002', N'ÍÑIGO', N'CÁRDENAS PRUEBA', N'19500220', N'M'),
 (N'CE', N'OF-CE-001', N'ÓSCAR', N'GUTIÉRREZ PRUEBA', N'19840405', N'M'),
 (N'CE', N'OF-CE-002', N'ÁNGELA', N'QUIÑONES PRUEBA', N'19550519', N'F'),
 (N'S/ DOCUMENTO', NULL, N'ANA', N'BELÉN PRUEBA', N'20200611', N'F'),
 (N'S/ DOCUMENTO', N'OF-SD-002', N'RAÚL', N'CASTAÑEDA PRUEBA', N'19900723', N'M'),
 (N'DIE', N'OF-DIE-001', N'ELENA', N'CHÁVEZ PRUEBA', N'19780913', N'F'),
 (N'DIE', N'OF-DIE-002', N'ANDRÉS', N'ACUÑA PRUEBA', N'20121021', N'M'),
 (N'PASS', N'OF-PASS-001', N'SOFÍA', N'ÁLVAREZ PRUEBA', N'19481130', N'F'),
 (N'PASS', N'OF-PASS-002', N'TOMÁS', N'DUEÑAS PRUEBA', N'20010101', N'M')
) AS [p] ([tipo_doc], [dni], [nombres], [apellidos], [nacimiento], [sexo])
WHERE NOT EXISTS (
 SELECT 1 FROM [dbo].[TB_PACIENTES_MOCK] WITH (UPDLOCK, HOLDLOCK)
 WHERE [tipo_doc] = [p].[tipo_doc] AND ([dni] = [p].[dni] OR ([dni] IS NULL AND [p].[dni] IS NULL))
);
COMMIT TRANSACTION;

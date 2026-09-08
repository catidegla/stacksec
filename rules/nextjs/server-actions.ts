"use server"

import { z } from "zod"
import { auth } from "@/lib/auth"
import { db } from "@/lib/db"

const schema = z.object({ id: z.string().uuid() })

// Reachable by anyone who can guess the action id. No caller check at all.
// ruleid: nextjs-server-action-without-authorization
// ruleid: nextjs-server-action-without-validation
export async function deleteProject(id: string) {
  await db.project.delete({ where: { id } })
}

// Validates its input and still has no idea who is calling. Validation is not
// authorization, and conflating them is the common version of this bug.
// ruleid: nextjs-server-action-without-authorization
export async function renameProject(input: unknown) {
  const { id } = schema.parse(input)
  await db.project.update({ where: { id }, data: { name: "x" } })
}

// Authenticated but unvalidated. The signature says string; the client can
// send anything.
// ruleid: nextjs-server-action-without-validation
export async function archiveProject(id: string) {
  const session = await auth()
  if (!session?.user) throw new Error("Unauthorized")

  await db.project.update({ where: { id }, data: { archived: true } })
}

// Authenticate, authorize the specific record, then validate.
// ok: nextjs-server-action-without-authorization
// ok: nextjs-server-action-without-validation
export async function deleteProjectSafely(input: unknown) {
  const session = await auth()
  if (!session?.user) throw new Error("Unauthorized")

  const { id } = schema.parse(input)

  const project = await db.project.findUnique({ where: { id } })
  if (project?.ownerId !== session.user.id) throw new Error("Forbidden")

  await db.project.delete({ where: { id } })
}

// A different auth library is still authorization.
// ok: nextjs-server-action-without-authorization
// ok: nextjs-server-action-without-validation
export async function publishPost(input: unknown) {
  const user = await currentUser()
  if (!user) throw new Error("Unauthorized")

  const { id } = schema.safeParse(input).data ?? {}
  await db.post.update({ where: { id }, data: { published: true } })
}

// No arguments, so there is nothing to validate. Only the auth rule applies.
// ok: nextjs-server-action-without-validation
// ok: nextjs-server-action-without-authorization
export async function signOutEverywhere() {
  const session = await auth()
  if (!session?.user) throw new Error("Unauthorized")

  await db.session.deleteMany({ where: { userId: session.user.id } })
}

declare function currentUser(): Promise<{ id: string } | null>

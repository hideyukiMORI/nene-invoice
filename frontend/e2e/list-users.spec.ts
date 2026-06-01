import { expect, test } from '@playwright/test'
import { json, login, problem } from './helpers/auth'

const USER = {
  id: 10,
  email: 'member@example.com',
  role: 'member',
  organization_id: 1,
  status: 'active',
  created_at: '2026-05-01 00:00:00',
  updated_at: '2026-05-01 00:00:00',
}

test.describe('User management', () => {
  test.describe('List users', () => {
    test('shows the user list after login', async ({ page }) => {
      await page.route('**/admin/users*', (route, request) => {
        if (request.method() === 'GET') {
          route.fulfill(json({ items: [USER], total: 1, limit: 100, offset: 0 }))
        } else {
          route.fallback()
        }
      })

      await login(page)
      await page.getByRole('link', { name: 'ユーザー' }).click()

      await expect(page.getByRole('heading', { name: 'ユーザー一覧' })).toBeVisible()
      await expect(page.getByText('member@example.com')).toBeVisible()
      await expect(page.getByText('メンバー')).toBeVisible()
    })

    test('shows empty state when no users', async ({ page }) => {
      await page.route('**/admin/users*', (route) =>
        route.fulfill(json({ items: [], total: 0, limit: 100, offset: 0 })),
      )

      await login(page)
      await page.getByRole('link', { name: 'ユーザー' }).click()

      await expect(page.getByText('ユーザーがまだいません。')).toBeVisible()
    })
  })

  test.describe('Create user', () => {
    test.beforeEach(async ({ page }) => {
      await page.route('**/admin/users*', (route, request) => {
        if (request.method() === 'GET') {
          route.fulfill(json({ items: [], total: 0, limit: 100, offset: 0 }))
        } else {
          route.fallback()
        }
      })

      await login(page)
      await page.getByRole('link', { name: 'ユーザー' }).click()
      await page.getByRole('link', { name: '新規作成' }).click()
      await expect(page.getByRole('heading', { name: 'ユーザーの作成' })).toBeVisible()
    })

    test('requires a valid email', async ({ page }) => {
      await page.locator('#password').fill('password123')
      await page.getByRole('button', { name: '作成する' }).click()

      await expect(page.getByText('有効なメールアドレスを入力してください。')).toBeVisible()
      await expect(page).toHaveURL(/\/users\/new$/)
    })

    test('requires a password of at least 8 characters', async ({ page }) => {
      await page.locator('#email').fill('new@example.com')
      await page.locator('#password').fill('short')
      await page.getByRole('button', { name: '作成する' }).click()

      await expect(page.getByText('パスワードは 8 文字以上')).toBeVisible()
    })

    test('creates a user and returns to the list', async ({ page }) => {
      await page.route('**/admin/users*', (route, request) => {
        if (request.method() === 'POST') {
          route.fulfill(json(USER, 201))
        } else {
          route.fulfill(json({ items: [], total: 0, limit: 100, offset: 0 }))
        }
      })

      await page.locator('#email').fill('member@example.com')
      await page.locator('#password').fill('password123')
      await page.getByRole('button', { name: '作成する' }).click()

      await expect(page).toHaveURL(/\/users$/)
    })

    test('shows error when server rejects creation', async ({ page }) => {
      await page.route('**/admin/users*', (route, request) => {
        if (request.method() === 'POST') {
          route.fulfill(problem('email-conflict', 409))
        } else {
          route.fulfill(json({ items: [], total: 0, limit: 100, offset: 0 }))
        }
      })

      await page.locator('#email').fill('dup@example.com')
      await page.locator('#password').fill('password123')
      await page.getByRole('button', { name: '作成する' }).click()

      await expect(page.getByText('ユーザーを作成できませんでした。')).toBeVisible()
    })
  })

  test.describe('Edit user', () => {
    test('prefills form and saves update', async ({ page }) => {
      await page.route('**/admin/users*', (route, request) => {
        if (request.method() === 'GET') {
          route.fulfill(json({ items: [USER], total: 1, limit: 100, offset: 0 }))
        } else {
          route.fallback()
        }
      })
      await page.route('**/admin/users/10', (route, request) => {
        if (request.method() === 'GET') {
          route.fulfill(json(USER))
        } else if (request.method() === 'PATCH') {
          route.fulfill(json({ ...USER, role: 'admin' }))
        } else {
          route.fallback()
        }
      })

      await login(page)
      await page.getByRole('link', { name: 'ユーザー' }).click()
      await page.getByRole('link', { name: '編集' }).first().click()

      await expect(page.getByRole('heading', { name: 'ユーザーの編集' })).toBeVisible()
      await expect(page.locator('#email')).toHaveValue('member@example.com')

      await page.getByRole('button', { name: '保存する' }).click()
      await expect(page).toHaveURL(/\/users$/)
    })
  })

  test.describe('Delete user', () => {
    test('deletes a user after confirmation', async ({ page }) => {
      await page.route('**/admin/users*', (route, request) => {
        if (request.method() === 'GET') {
          route.fulfill(json({ items: [USER], total: 1, limit: 100, offset: 0 }))
        } else {
          route.fallback()
        }
      })
      await page.route('**/admin/users/10', (route, request) => {
        if (request.method() === 'DELETE') {
          route.fulfill({ status: 204 })
        } else {
          route.fallback()
        }
      })

      await login(page)
      await page.getByRole('link', { name: 'ユーザー' }).click()
      await page.getByRole('button', { name: '削除' }).first().click()

      await expect(page.getByText('ユーザーを削除しますか？')).toBeVisible()
      await page.getByRole('button', { name: '削除する' }).click()

      await expect(page.getByText('ユーザーを削除しますか？')).not.toBeVisible()
    })
  })
})

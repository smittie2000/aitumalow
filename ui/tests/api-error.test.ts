import assert from 'node:assert/strict'
import test from 'node:test'
import { ApiError, apiErrorDetails, apiErrorMessage } from '../src/api/client.ts'

test('apiErrorMessage includes workflow validation details returned by the API', () => {
  const error = new ApiError(
    422,
    ['Workflow must have at least one trigger node.'],
    'Workflow validation failed.',
  )

  assert.equal(
    apiErrorMessage(error, 'Status change failed.'),
    'Workflow validation failed. Workflow must have at least one trigger node.',
  )
})

test('apiErrorMessage supports Laravel field validation errors', () => {
  const error = new ApiError(
    422,
    { name: ['The name field is required.'] },
    'The given data was invalid.',
  )

  assert.equal(
    apiErrorMessage(error, 'Request failed.'),
    'The given data was invalid. The name field is required.',
  )
})

test('apiErrorDetails preserves structured messages for canvas validation', () => {
  const error = new ApiError(
    422,
    ["Node 'Send email' (id:42): The recipient field is required."],
    'Workflow validation failed.',
  )

  assert.deepEqual(apiErrorDetails(error), [
    "Node 'Send email' (id:42): The recipient field is required.",
  ])
  assert.deepEqual(apiErrorDetails(new Error('Network unavailable')), [])
})

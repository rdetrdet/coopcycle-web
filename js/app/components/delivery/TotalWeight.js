import React, { useEffect, useState } from 'react'
import { InputNumber, Select } from 'antd'
const { Option } = Select
import { useFormikContext } from 'formik'

export default ({ index }) => {
  const { setFieldValue, errors } = useFormikContext()

  const [numberValue, setNumberValue] = useState(null)
  const [weightUnit, setWeightUnit] = useState('kg')

  useEffect(() => {
    if (numberValue !== null) {
      let calculatedWeight = 0
      if (weightUnit === 'kg') {
        calculatedWeight = numberValue * 1000
      } else if (weightUnit === 'lbs') {
        calculatedWeight = numberValue * 453.592
      }
      setFieldValue(`tasks[${index}].weight`, calculatedWeight)
    } else {
      setFieldValue(`tasks[${index}].weight`, 0)
    }
  }, [numberValue, weightUnit, index])



  return (
    <div className="mt-4 mb-4">
      <div className="mb-2 font-weight-bold">Total Weight</div>
      <div style={{ display: 'flex', justifyContent: 'space-between' }}>
        <InputNumber
          style={{ width: '80%' }}
          min={0}
          placeholder="Weight"
          value={numberValue}
          onChange={value => {
            setNumberValue(value)
          }}
        />
        <Select
          style={{ width: '15%' }}
          value={weightUnit}
          onChange={value => setWeightUnit(value)}>
          <Option value="kg">Kg</Option>
          <Option value="lbs">Lbs</Option>
        </Select>
      </div>

      {errors.tasks?.[index]?.weight && (
        <div className="text-danger">{errors.tasks[index].weight}</div>
      )}
    </div>
  )
}

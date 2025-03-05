import React, { useState, useEffect, useCallback } from 'react'
import { DatePicker, Select, Radio } from 'antd'
import moment from 'moment'
import { useFormikContext } from 'formik'
import { useTranslation } from 'react-i18next'

import './TimeSlotPicker.scss'
import Spinner from '../core/Spinner'

const baseURL = location.protocol + '//' + location.host

export default ({ storeId, storeDeliveryInfos, index }) => {
  const httpClient = new window._auth.httpClient()

  const { t } = useTranslation()

  const { setFieldValue, values } = useFormikContext()

  const [storeDeliveryLabels, setStoreDeliveryLabels] = useState(null)

  useEffect(() => {
    const getTimeSlotsLabels = async () => {
      const url = `${baseURL}/api/stores/${storeId}/time_slots`

      const { response } = await httpClient.get(url)

      if (response) {
        const timeSlotsLabel = response['hydra:member']
        setStoreDeliveryLabels(timeSlotsLabel)
      }
    }
    getTimeSlotsLabels()

    const timeSlotUrl = storeDeliveryInfos.timeSlot
    getTimeSlotOptions(timeSlotUrl)

  }, [storeDeliveryInfos])

  /** We get the labels available and the default label for the radio buttons */

  const getTimeSlotNames = useCallback(() => {
    if (storeDeliveryLabels) {
      const timeSlotNames = []
      for (const label of storeDeliveryLabels) {
        timeSlotNames.push(label.name)
      }
  
      return timeSlotNames
    }
  }, [storeDeliveryLabels])

  const timeSlotNames = getTimeSlotNames()

  const getDefaultLabel = useCallback(() => {
    if (storeDeliveryLabels) {
      const defaultLabel = storeDeliveryLabels.find(
        label => label['@id'] === storeDeliveryInfos.timeSlot,
      )
      return defaultLabel
    }
  }, [storeDeliveryLabels])

  const defaultLabel = getDefaultLabel()

  /** We initialize with the default timesSlots, then changed when user selects a different option */

  const [timeSlotChoices, setTimeSlotChoices] = useState([])

  const getTimeSlotOptions = async timeSlotUrl => {
    const url = `${baseURL}${timeSlotUrl}/choices`
    const { response } = await httpClient.get(url)
    if (response) {
      setTimeSlotChoices(response['choices'])
    }
  }

  /** We format the data in order for them to fit in a datepicker and a select
   * We initialize the datepicker's and the select's values
   */

  const [datesWithTimeslots, setDatesWithTimeslots] = useState({})
  const [selectedValues, setSelectedValues] = useState({})
  const [options, setOptions] = useState([])

  useEffect(() => {
    const formatTimeSlots = () => {
      const formattedSlots = {}
      timeSlotChoices.forEach(choice => {
        let [first, second] = choice.value.split('/')
        first = moment(first)
        second = moment(second)
        const date = moment(first).format('YYYY-MM-DD')
        const hour = `${first.format('HH:mm')}-${second.format('HH:mm')}`
        if (formattedSlots[date]) {
          formattedSlots[date].push(hour)
        } else {
          formattedSlots[date] = [hour]
        }
      })
      setDatesWithTimeslots(formattedSlots)

      const availableDates = Object.keys(formattedSlots)
      if (availableDates.length > 0) {
        const firstDate = moment(availableDates[0])
        setOptions(formattedSlots[availableDates[0]])
        setSelectedValues({
          date: firstDate,
          option: formattedSlots[availableDates[0]][0],
        })
      }
    }
    formatTimeSlots()
  }, [timeSlotChoices])

  useEffect(() => {
    if (Object.keys(selectedValues).length !== 0) {
      const date = selectedValues.date.format('YYYY-MM-DD')
      const range = selectedValues.option
      const [first, second] = range.split('-')
      const timeSlot = `${date}T${first}:00Z/${date}T${second}:00Z`
      setFieldValue(`tasks[${index}].timeSlot`, timeSlot)
    }
  }, [selectedValues])

  /** disabled dates */

  const dates = Object.keys(datesWithTimeslots || {}).map(date => moment(date))

  function disabledDate(current) {
    return !dates.some(date => date.isSame(current, 'day'))
  }

  const handleTimeSlotLabelChange = e => {
    const label = storeDeliveryLabels.find(
      label => label.name === e.target.value,
    )
    const timeSlotUrl = label['@id']
    setFieldValue(`tasks[${index}].timeSlotName`, label.name)
    getTimeSlotOptions(timeSlotUrl)
  }

  const handleDateChange = newDate => {
    if (!newDate) return

    setSelectedValues({
      date: newDate,
      option: datesWithTimeslots[newDate.format('YYYY-MM-DD')][0],
    })
    setOptions(datesWithTimeslots[newDate.format('YYYY-MM-DD')])
  }

  const handleTimeSlotChange = newTimeslot => {
    if (!newTimeslot) return
    setSelectedValues(prevState => ({ ...prevState, option: newTimeslot }))
  }

  if (!storeDeliveryLabels) {
    return <Spinner />
  }

  return (
    <>
      <div className="mb-2 font-weight-bold title-slot">
        {t('ADMIN_DASHBOARD_FILTERS_TAB_TIMERANGE')}
      </div>
      {defaultLabel && timeSlotNames ? (
        <Radio.Group
          className="timeslot__container mb-2"
          defaultValue={defaultLabel.name}
          value={values.tasks[index].timeSlotName || defaultLabel.name}
        >
          {timeSlotNames.map(label => (
            <Radio.Button
              key={label}
              value={label}
              onChange={timeSlot => {
                handleTimeSlotLabelChange(timeSlot)
              }}>
              {label}
            </Radio.Button>
          ))}
        </Radio.Group>
      ) : null}

      <div style={{ display: 'flex', marginTop: '0.5em' }}>
        {selectedValues.date ? (
          <DatePicker
            format="LL"
            style={{ width: '60%' }}
            className="mr-2"
            disabledDate={disabledDate}
            disabled={dates.length > 1 ? false : true}
            value={selectedValues.date}
            onChange={date => {
              handleDateChange(date)
            }}
          />
        ) : null}

        {selectedValues.option && options ? (
          <Select
            style={{ width: '35%' }}
            onChange={option => {
              handleTimeSlotChange(option)
            }}
            value={selectedValues.option}>
            {options.length >= 1 &&
              options.map(option => (
                <Select.Option key={option} value={option}>
                  {option}
                </Select.Option>
              ))}
          </Select>
        ) : null}
      </div>
    </>
  )
}

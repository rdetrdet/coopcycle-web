import React, { useState } from 'react'
import { Modal, Card, Tag, Descriptions, Col, Row, Tooltip } from 'antd'
import _ from 'lodash'


function _generateLabelURL(barcode) {
  return window.Routing.generate("task_label_pdf") + "?code=" + barcode;
}

function GenericBarcode({ barcode }) {
  return (
    <Card
      title="Task"
      size="small"
      headStyle={{ backgroundColor: '#f0f2f5' }}
      extra={<Tooltip title="This is a generic barcode for this dropoff, this barcode can be used for all purposes.">
        <i className="fa fa-question-circle" aria-hidden="true"></i>
      </Tooltip>}
      actions={[<a href={_generateLabelURL(barcode)} target="_blank">View</a>]}>
      <p>Tracking: {barcode}</p>
    </Card>
  )
}

function PackageBarcode({ barcode, label, index }) {
  console.log(barcode)
  return (
    <Card
      title={`Package #${index}`}
      size="small"
      extra={<Tag>{label}</Tag>}
      actions={[<a href={_generateLabelURL(barcode)} target="_blank">View</a>]}>
      <p>Tracking: {barcode}</p>
    </Card>
  )
}

function TaskBarcode({ index, task }) {
  const packages = _(task.barcodes.packages).reduce((acc, p) => {
    acc = acc.concat(p.barcodes.map(b => [p.name, b]));
    return acc
  }, []);
  return (
    <>
      {index > 1 && <hr />}
      <Descriptions
        bordered={true}
        className="mb-3"
        title={`Drop-off #${index}`}
        layout="vertical">
        <Descriptions.Item label="Recipient">
          {task.address.contactName}
        </Descriptions.Item>
        <Descriptions.Item label="Address">
          {task.address.streetAddress}
        </Descriptions.Item>
      </Descriptions>
      <Row gutter={[16, 16]}>
        <Col span={8}>
          <GenericBarcode barcode={task.barcodes.task} />
        </Col>
        {packages.length &&
          packages.map(([ name, barcode ], index) => (
            <Col span={8}>
              <PackageBarcode
                label={name}
                barcode={barcode}
                index={index + 1}
              />
            </Col>
          ))}
      </Row>
    </>
  )
}

export default function ({ items }) {
  const _items = _(JSON.parse(items))
    .sortBy('position')
    .map(i => i.task)
    .filter(i => i.type !== 'PICKUP')
    .value()
  const [isOpen, setIsOpen] = useState(false)

  return (
    <>
      <a href="#" onClick={() => setIsOpen(true)}>
        Show barcodes
      </a>
      <Modal
        title="Barcodes"
        open={isOpen}
        onCancel={() => setIsOpen(false)}
        width="980px">
        {_items.map((item, index) => (
          <TaskBarcode key={index} index={index + 1} task={item} />
        ))}
      </Modal>
    </>
  )
}
